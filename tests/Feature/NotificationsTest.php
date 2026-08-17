<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Conversation;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\AsistenciaService;
use App\Services\MatriculaService;
use App\Services\NotificationService;
use App\Services\StockAlertService;
use App\Support\GymContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::create(['name' => 'Sparta Gym', 'slug' => 'sparta-gym', 'is_active' => true]);
        GymContext::set($this->gym);
    }

    protected function tearDown(): void
    {
        GymContext::forget();
        parent::tearDown();
    }

    /* ---------------------------------------------------------- */
    /* Helpers                                                    */
    /* ---------------------------------------------------------- */

    private function rol(string $slug): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'level' => 10]);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create([
            'gym_id'    => $this->gym->id,
            'role_id'   => $this->rol($rol)->id,
            'is_active' => true,
        ]);
    }

    private function socio(?User $conCuenta = null): Member
    {
        return Member::create([
            'gym_id'     => $this->gym->id,
            'user_id'    => $conCuenta?->id,
            'first_name' => 'Ana',
            'last_name'  => 'Torres',
            'status'     => 'activo',
            'code'       => 'SP-' . random_int(1000, 9999),
        ]);
    }

    private function plan(): Plan
    {
        return Plan::create([
            'gym_id'        => $this->gym->id,
            'name'          => 'Mensual',
            'slug'          => 'mensual',
            'price'         => 100,
            'duration_days' => 30,
            'is_active'     => true,
            'is_public'     => true,
            'sort_order'    => 1,
        ]);
    }

    /* ---------------------------------------------------------- */
    /* Servicio                                                   */
    /* ---------------------------------------------------------- */

    public function test_dedupe_por_tipo_y_sujeto_mientras_este_sin_leer(): void
    {
        $admin = $this->usuario('admin');
        $servicio = app(NotificationService::class);

        $n1 = $servicio->disparar($admin, 'venta.nueva', 'Título', 'Cuerpo', 'billetera', 'media', 42);
        $n2 = $servicio->disparar($admin, 'venta.nueva', 'Actualizado', 'Nuevo cuerpo', 'billetera', 'media', 42);

        $this->assertSame($n1->id, $n2->id, 'El mismo sujeto sin leer no debe duplicar fila.');
        $this->assertSame('Actualizado', $n2->title, 'La fila existente se refresca con el nuevo contenido.');
        $this->assertSame(1, Notification::count());

        // Sujeto distinto → fila nueva.
        $servicio->disparar($admin, 'venta.nueva', 'Otra', 'Cuerpo', 'billetera', 'media', 43);
        $this->assertSame(2, Notification::count());
    }

    public function test_contador_y_marcar_todas_como_leidas(): void
    {
        $admin = $this->usuario('admin');
        $servicio = app(NotificationService::class);

        $servicio->disparar($admin, 'stock.agotado', 'A', 'B', 'caja', 'alta', 1);
        $servicio->disparar($admin, 'venta.nueva', 'A', 'B', 'billetera', 'media', 2);

        $this->assertSame(2, $servicio->noLeidas($admin));

        $servicio->marcarTodasLeidas($admin);

        $this->assertSame(0, $servicio->noLeidas($admin));
        $this->assertTrue(Notification::all()->every(fn ($n) => $n->read_at !== null));
    }

    public function test_vigencia_de_24_horas_filtra_y_limpia(): void
    {
        $admin = $this->usuario('admin');
        $servicio = app(NotificationService::class);

        $servicio->disparar($admin, 'venta.nueva', 'A', 'B', 'billetera', 'media', 1);

        // Una fila vieja (25 h) no cuenta ni aparece, y el comando la borra.
        Notification::query()->update(['created_at' => now()->subHours(25)]);

        $this->assertSame(0, $servicio->noLeidas($admin), 'Lo vencido no cuenta en el badge.');
        $this->assertSame([], $servicio->lista($admin), 'Lo vencido no aparece en el cajón.');

        $borradas = $servicio->limpiarVencidas();
        $this->assertSame(1, $borradas);
        $this->assertSame(0, Notification::count());
    }

    public function test_el_actor_no_recibe_su_propia_venta(): void
    {
        $admin = $this->usuario('admin');
        $recepcion = $this->usuario('recepcion');

        $venta = Sale::create([
            'sale_type' => 'producto',
            'sold_by'   => $admin->id,
            'number'    => 'V-000001',
            'subtotal'  => 10,
            'total'     => 10,
            'method'    => 'efectivo',
            'status'    => 'completada',
            'sold_at'   => now(),
        ]);

        $this->assertNotNull($venta);
        $this->assertSame(0, app(NotificationService::class)->noLeidas($admin), 'El vendedor no se auto-notifica.');
        $this->assertSame(1, app(NotificationService::class)->noLeidas($recepcion), 'El resto del staff sí se entera.');
    }

    /* ---------------------------------------------------------- */
    /* Emisores                                                   */
    /* ---------------------------------------------------------- */

    public function test_mensaje_nuevo_notifica_al_otro_participante(): void
    {
        $entrenador = $this->usuario('entrenador');
        $cliente    = $this->usuario('cliente');

        $hilo = Conversation::create();
        $hilo->participants()->createMany([
            ['user_id' => $entrenador->id],
            ['user_id' => $cliente->id],
        ]);

        $hilo->messages()->create(['sender_id' => $entrenador->id, 'body' => 'Nos vemos mañana']);

        $this->assertSame(1, app(NotificationService::class)->noLeidas($cliente), 'El receptor recibe un aviso.');
        $this->assertSame(0, app(NotificationService::class)->noLeidas($entrenador), 'El emisor no se auto-notifica.');

        // Abrir el hilo marca como leída la fila de la campanita.
        app(NotificationService::class)->marcarLeidasDeTipo($cliente, 'mensaje.nuevo', $hilo->id);
        $this->assertSame(0, app(NotificationService::class)->noLeidas($cliente));
    }

    public function test_stock_bajo_notifica_y_reponer_lo_resuelve(): void
    {
        $admin = $this->usuario('admin');
        $servicio = app(NotificationService::class);

        $producto = Product::create([
            'name'       => 'Creatina 300 g',
            'sku'        => 'SP-001',
            'cost_price' => 40,
            'sale_price' => 60,
            'stock'      => 0,
            'min_stock'  => 2,
            'is_active'  => true,
        ]);

        $this->assertSame(1, $servicio->noLeidas($admin), 'Agotado → aviso al staff.');
        $this->assertTrue(
            Notification::where('user_id', $admin->id)->where('type', 'stock.agotado')->where('subject_id', $producto->id)->exists(),
        );

        // Reponer vuelve a normal: la alerta y su aviso desaparecen.
        $producto->update(['stock' => 10]);
        $this->assertSame(0, $servicio->noLeidas($admin), 'Al reponer, el aviso se resuelve solo.');
    }

    public function test_solicitud_de_asistencia_notifica_y_el_resultado_avisa_al_solicitante(): void
    {
        $admin      = $this->usuario('admin');
        $entrenador = $this->usuario('entrenador');
        $socio      = $this->socio();

        $asistencia = Attendance::create([
            'member_id'     => $socio->id,
            'registered_by' => $entrenador->id,
            'checked_in_at' => now()->subHour(),
            'method'        => 'manual',
        ]);

        $servicio = app(AsistenciaService::class);
        $solicitud = $servicio->solicitarCorreccion($asistencia, $entrenador, [
            'checked_in_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'reason'        => 'Marcó 8:00 y entró 8:20',
        ]);

        $this->assertSame(1, app(NotificationService::class)->noLeidas($admin), 'La solicitud pendiente avisa al admin.');

        $servicio->aprobar($solicitud, $admin);

        $this->assertSame(1, app(NotificationService::class)->noLeidas($entrenador), 'El entrenador se entera del resultado.');
        $this->assertTrue(
            Notification::where('user_id', $entrenador->id)->where('type', 'asistencia.resuelta')->exists(),
        );
    }

    public function test_matricula_nueva_notifica_al_staff_que_no_la_hizo(): void
    {
        $admin      = $this->usuario('admin');
        $entrenador = $this->usuario('entrenador');

        $resultado = app(MatriculaService::class)->nuevaMatricula(
            ['first_name' => 'Luis', 'last_name' => 'Paredes'],
            $this->plan(),
            ['starts_at' => now()->toDateString(), 'discount' => 0, 'registrar_pago' => false],
            $entrenador,
        );

        $this->assertNotNull($resultado['member']);
        $this->assertSame(1, app(NotificationService::class)->noLeidas($admin), 'La matrícula de un entrenador avisa al admin.');
    }

    public function test_comando_de_vencimientos_avisa_al_socio(): void
    {
        $cliente = $this->usuario('cliente');
        $socio   = $this->socio($cliente);

        $socio->memberships()->create([
            'plan_id'    => $this->plan()->id,
            'created_by' => $cliente->id,
            'plan_name'  => 'Mensual',
            'price'      => 100,
            'starts_at'  => now()->subDays(27),
            'ends_at'    => now()->addDays(3),
            'status'     => 'activa',
        ]);

        $this->artisan('notificaciones:vencimientos')->assertSuccessful();

        $this->assertSame(1, app(NotificationService::class)->noLeidas($cliente), 'El socio recibe el aviso de vencimiento.');
        $this->assertTrue(
            Notification::where('user_id', $cliente->id)->where('type', 'membresia.por-vencer')->exists(),
        );
    }

    /* ---------------------------------------------------------- */
    /* Endpoints                                                  */
    /* ---------------------------------------------------------- */

    public function test_endpoints_de_la_campanita(): void
    {
        $admin  = $this->usuario('admin');
        $otro   = $this->usuario('cliente');
        $servicio = app(NotificationService::class);

        $n = $servicio->disparar($admin, 'venta.nueva', 'Venta registrada', 'Venta V-000001 · S/ 10', 'billetera', 'media', 1);

        $this->actingAs($admin)->getJson('/notificaciones/total')->assertOk()->assertJson(['total' => 1]);

        $this->actingAs($admin)->getJson('/notificaciones')
            ->assertOk()
            ->assertJsonPath('items.0.title', 'Venta registrada');

        $this->actingAs($admin)->getJson('/notificaciones/nuevas?desde=0')
            ->assertOk()
            ->assertJsonStructure(['ultimo_id', 'toasts'])
            ->assertJsonPath('toasts.0.priority', 'media');

        // Otra persona no puede marcar la fila de un tercero.
        $this->actingAs($otro)->postJson('/notificaciones/' . $n->id . '/leida')->assertForbidden();

        $this->actingAs($admin)->postJson('/notificaciones/' . $n->id . '/leida')->assertOk();
        $this->actingAs($admin)->getJson('/notificaciones/total')->assertJson(['total' => 0]);

        $servicio->disparar($admin, 'stock.bajo', 'Stock bajo', 'Creatina', 'caja', 'media', 1);
        $this->actingAs($admin)->postJson('/notificaciones/leidas')->assertOk()->assertJson(['total' => 1]);
        $this->actingAs($admin)->getJson('/notificaciones/total')->assertJson(['total' => 0]);
    }
}
