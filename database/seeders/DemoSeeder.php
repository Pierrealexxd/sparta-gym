<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceEditRequest;
use App\Models\Gym;
use App\Models\GymQrCode;
use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Role;
use App\Models\StaffAttendance;
use App\Models\Trainer;
use App\Models\User;
use App\Support\GymContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de demostración: cuentas de acceso, clientes con historial y un año de
 * movimiento para que el dashboard tenga algo real que graficar.
 *
 * No es contenido de producción. `php artisan migrate:fresh --seed` lo
 * regenera entero.
 */
class DemoSeeder extends Seeder
{
    private Gym $gym;

    public function run(): void
    {
        $this->gym = Gym::where('slug', config('sparta.gym_slug'))->firstOrFail();
        GymContext::set($this->gym);

        $this->cuentas();
        $entrenadores = $this->entrenadores();
        $this->clientes($entrenadores);
        $this->productos();
        $this->marcacionesDeStaff();
        $this->qrDeAsistencia();
        $this->asistenciasRegistradasPorEntrenadores();
        $this->solicitudesDemo();
    }

    /* ---------------------------------------------------------- */

    private function cuentas(): void
    {
        $cuentas = [
            ['admin',      'Administrador Sparta', 'admin@spartagym.pe'],
            ['recepcion',  'Recepción',            'recepcion@spartagym.pe'],
        ];

        foreach ($cuentas as [$rol, $nombre, $email]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'gym_id'   => $this->gym->id,
                    'role_id'  => Role::where('slug', $rol)->value('id'),
                    'name'     => $nombre,
                    'password' => 'sparta2026',   // el cast 'hashed' lo cifra
                    'is_active'=> true,
                ]
            );
        }
    }

    /** @return array<int, Trainer> */
    private function entrenadores(): array
    {
        $datos = [
            ['Kevin Alvarado', 'kevin@spartagym.pe',  'Fuerza y powerlifting', 8,
             'Compitió en powerlifting seis años. Ahora enseña a levantar sin romperse.',
             ['Entrenador personal certificado', 'Especialista en levantamiento olímpico']],
            ['Rosa Medina',    'rosa@spartagym.pe',   'Pérdida de grasa y nutrición', 6,
             'Nutricionista y entrenadora. Cree poco en las dietas y mucho en los hábitos.',
             ['Licenciada en Nutrición', 'Entrenadora personal certificada']],
            ['Bruno Castillo', 'bruno@spartagym.pe',  'Funcional y acondicionamiento', 5,
             'Viene del rugby. Entrena gente para que el cuerpo aguante la vida diaria.',
             ['Preparador físico', 'Especialista en entrenamiento funcional']],
        ];

        $rolEntrenador = Role::where('slug', 'entrenador')->value('id');
        $entrenadores = [];

        foreach ($datos as $i => [$nombre, $email, $especialidad, $anios, $bio, $certificados]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'gym_id'   => $this->gym->id,
                    'role_id'  => $rolEntrenador,
                    'name'     => $nombre,
                    'password' => 'sparta2026',
                    'is_active'=> true,
                ]
            );

            $entrenadores[] = Trainer::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'gym_id'           => $this->gym->id,
                    'specialty'        => $especialidad,
                    'bio'              => $bio,
                    'years_experience' => $anios,
                    'certifications'   => $certificados,
                    'is_public'        => true,
                    'is_active'        => true,
                    'sort_order'       => $i,
                ]
            );
        }

        return $entrenadores;
    }

    /* ---------------------------------------------------------- */

    /** @param array<int, Trainer> $entrenadores */
    private function clientes(array $entrenadores): void
    {
        if (Member::count() > 0) {
            return;   // ya sembrado; no duplicamos historial
        }

        $planes = Plan::where('duration_days', '>', 1)->get();
        $rolCliente = Role::where('slug', 'cliente')->value('id');

        $clientes = Member::factory()->count(120)->create();

        foreach ($clientes as $i => $socio) {
            // Uno de cada tres tiene cuenta para entrar a su panel.
            if ($i % 3 === 0) {
                $user = User::create([
                    'gym_id'   => $this->gym->id,
                    'role_id'  => $rolCliente,
                    'name'     => $socio->full_name,
                    'email'    => $socio->email,
                    'password' => 'sparta2026',
                    'is_active'=> true,
                ]);
                $socio->update(['user_id' => $user->id]);
            }

            $this->historialDelSocio($socio, $planes->random());

            // Un entrenador asignado a la mitad de los clientes activos.
            if ($socio->status === 'activo' && $i % 2 === 0) {
                $socio->assignments()->create([
                    'trainer_id'  => $entrenadores[$i % count($entrenadores)]->id,
                    'assigned_at' => $socio->joined_at,
                ]);
            }
        }
    }

    /**
     * Reconstruye la vida de un socio: membresías encadenadas desde su alta
     * hasta hoy, con su pago y sus asistencias. Es lo que hace que los
     * gráficos del dashboard tengan forma de gimnasio real y no de ruido.
     */
    private function historialDelSocio(Member $socio, Plan $plan): void
    {
        $inicio = Carbon::parse($socio->joined_at);
        $anterior = null;

        // Los inactivos dejaron de renovar en algún momento del pasado.
        $limite = $socio->status === 'activo'
            ? now()
            : now()->subDays(random_int(20, 200));

        while ($inicio->lt($limite)) {
            $fin = $inicio->copy()->addDays($plan->duration_days);

            $membresia = Membership::create([
                'gym_id'      => $this->gym->id,
                'member_id'   => $socio->id,
                'plan_id'     => $plan->id,
                'renewed_from'=> $anterior?->id,
                'plan_name'   => $plan->name,
                'price'       => $plan->price,
                'starts_at'   => $inicio->toDateString(),
                'ends_at'     => $fin->toDateString(),
                'status'      => $fin->isFuture() ? 'activa' : 'vencida',
            ]);

            Sale::create([
                'gym_id'        => $this->gym->id,
                'member_id'     => $socio->id,
                'sale_type'     => 'membresia',
                'membership_id' => $membresia->id,
                'number'        => Sale::siguienteNumero(),
                'subtotal'      => $plan->price,
                'discount'      => 0,
                'total'         => $plan->price,
                'concept'       => "Membresía {$plan->name}",
                'method'        => fake()->randomElement(['efectivo', 'efectivo', 'yape', 'yape', 'plin', 'transferencia', 'tarjeta']),
                'status'        => 'completada',
                'sold_at'       => $inicio->copy()->addHours(random_int(7, 20)),
            ]);

            $this->asistencias($socio, $inicio, min($fin, $limite));

            $anterior = $membresia;
            $inicio = $fin->copy();
        }

        $this->medidas($socio);
    }

    private function asistencias(Member $socio, Carbon $desde, Carbon $hasta): void
    {
        // Constancia propia de cada socio: entre 2 y 6 visitas por semana.
        $porSemana = random_int(2, 6);
        $cursor = $desde->copy();
        $filas = [];

        while ($cursor->lt($hasta)) {
            if (random_int(1, 7) <= $porSemana) {
                $entrada = $cursor->copy()->setTime(random_int(6, 20), random_int(0, 59));

                $filas[] = [
                    'gym_id'         => $this->gym->id,
                    'member_id'      => $socio->id,
                    'checked_in_at'  => $entrada,
                    'checked_out_at' => $entrada->copy()->addMinutes(random_int(45, 110)),
                    'method'         => fake()->randomElement(['qr', 'qr', 'qr', 'codigo', 'busqueda']),
                    'created_at'     => $entrada,
                    'updated_at'     => $entrada,
                ];
            }
            $cursor->addDay();
        }

        // Inserción por lotes: 120 clientes × años de historial son decenas de
        // miles de filas, y una a una el seeder tardaría minutos.
        foreach (array_chunk($filas, 500) as $lote) {
            Attendance::insert($lote);
        }
    }

    private function medidas(Member $socio): void
    {
        $peso = $socio->gender === 'M'
            ? fake()->randomFloat(1, 68, 105)
            : fake()->randomFloat(1, 52, 88);

        $fecha = Carbon::parse($socio->joined_at);
        $tendencia = fake()->randomElement([-0.8, -0.4, 0.3, 0.6]);   // kg por toma

        while ($fecha->lt(now())) {
            MemberMeasurement::create([
                'member_id'   => $socio->id,
                'measured_at' => $fecha->toDateString(),
                'weight_kg'   => round($peso, 1),
                'height_cm'   => $socio->height_cm,
                'body_fat_pct'=> fake()->randomFloat(1, 10, 32),
            ]);

            $peso += $tendencia + fake()->randomFloat(1, -0.6, 0.6);
            $fecha->addMonth();
        }
    }

    /* ---------------------------------------------------------- */

    /** @return array<int, User> */
    private function entrenadoresUsers(): array
    {
        return User::whereHas('role', fn ($q) => $q->where('slug', 'entrenador'))->get()->all();
    }

    /**
     * Marcaciones LABORALES de los entrenadores del último mes: turnos
     * de mañana/tarde/doble, con un día de descanso a la semana. Llena el
     * calendario de "Mi marcación" y el panel de Personal del admin.
     */
    private function marcacionesDeStaff(): void
    {
        $entrenadores = $this->entrenadoresUsers();
        $turnos = ['manana', 'manana', 'tarde', 'tarde', 'doble'];

        foreach ($entrenadores as $entrenador) {
            $cursor = now()->subDays(30)->startOfDay();

            while ($cursor->lte(now())) {
                // Un descanso a la semana, caiga donde caiga.
                if ($cursor->dayOfWeek % 7 === 4 || random_int(1, 5) === 5) {
                    $cursor->addDay();
                    continue;
                }

                $turno = fake()->randomElement($turnos);
                $entrada = $cursor->copy()->setTime(
                    $turno === 'tarde' ? random_int(13, 15) : random_int(6, 9),
                    random_int(0, 59),
                );
                $salida = $entrada->copy()->addHours($turno === 'doble' ? random_int(9, 11) : random_int(4, 6));

                if ($salida->gt(now())) {
                    $salida = now();
                }

                StaffAttendance::create([
                    'gym_id'         => $this->gym->id,
                    'user_id'        => $entrenador->id,
                    'clocked_in_at'  => $entrada,
                    'clocked_out_at' => $salida,
                    'turno'          => $turno,
                    'method'         => fake()->randomElement(['manual', 'manual', 'manual', 'qr']),
                ]);

                $cursor->addDay();
            }
        }
    }

    /**
     * Un QR de asistencia vigente para la sede demo, para que el entrenador
     * pueda probar el escaneo nada más entrar. Idempotente: revoca los
     * anteriores (mismo efecto que "Regenerar" en el panel) y emite uno nuevo.
     */
    private function qrDeAsistencia(): void
    {
        $this->gym->qrCodes()->vigente()->update([
            'is_active'  => false,
            'revoked_at' => now(),
        ]);

        GymQrCode::create([
            'gym_id'     => $this->gym->id,
            'label'      => 'QR de asistencia',
            'created_by' => User::where('email', 'admin@spartagym.pe')->value('id'),
        ]);
    }

    /**
     * El panel del entrenador vive de las asistencias que él mismo registró
     * (registered_by), así que una parte de las asistencias recientes se
     * reparte entre los entrenadores para que su calendario no arranque vacío.
     */
    private function asistenciasRegistradasPorEntrenadores(): void
    {
        $entrenadores = $this->entrenadoresUsers();

        if ($entrenadores === []) {
            return;
        }

        Attendance::where('checked_in_at', '>=', now()->subDays(14))
            ->inRandomOrder()
            ->limit(150)
            ->get()
            ->each(function (Attendance $a) use ($entrenadores) {
                $a->update(['registered_by' => fake()->randomElement($entrenadores)->id]);
            });
    }

    /** Un par de solicitudes de corrección en cola para que la campanita tenga algo. */
    private function solicitudesDemo(): void
    {
        $entrenador = $this->entrenadoresUsers()[0] ?? null;

        if (! $entrenador) {
            return;
        }

        $marcacion = StaffAttendance::where('user_id', $entrenador->id)
            ->whereNotNull('clocked_out_at')
            ->latest('clocked_in_at')
            ->first();

        if ($marcacion) {
            AttendanceEditRequest::create([
                'gym_id'             => $this->gym->id,
                'staff_attendance_id'=> $marcacion->id,
                'requested_by'       => $entrenador->id,
                'checked_in_at'      => $marcacion->clocked_in_at->copy()->subMinutes(15),
                'checked_out_at'     => $marcacion->clocked_out_at,
                'reason'             => 'Llegué antes de lo que marca el registro.',
                'status'             => 'pendiente',
            ]);
        }

        $asistencia = Attendance::whereNotNull('registered_by')->latest('checked_in_at')->first();

        if ($asistencia) {
            AttendanceEditRequest::create([
                'gym_id'          => $this->gym->id,
                'attendance_id'   => $asistencia->id,
                'requested_by'    => $asistencia->registered_by,
                'checked_in_at'   => $asistencia->checked_in_at->copy()->addMinutes(10),
                'checked_out_at'  => $asistencia->checked_out_at,
                'reason'          => 'Corregí la hora de entrada en recepción.',
                'status'          => 'pendiente',
            ]);
        }
    }

    private function productos(): void
    {
        $productos = [
            ['Proteína whey 2 kg',      'proteina',   85,  145, 18, 5],
            ['Creatina monohidrato',    'suplemento', 45,   80, 24, 6],
            ['Pre-entreno 300 g',       'suplemento', 55,   95, 12, 4],
            ['BCAA 250 g',              'suplemento', 40,   70,  9, 4],
            ['Shaker Sparta',           'accesorio',  10,   25, 40, 10],
            ['Guantes de agarre',       'accesorio',  18,   40, 15, 5],
            ['Cinturón de fuerza',      'accesorio',  60,  120,  6, 3],
            ['Straps de muñeca',        'accesorio',  12,   28, 20, 6],
            ['Agua 625 ml',             'bebida',      1,    3, 90, 24],
            ['Bebida isotónica',        'bebida',      2,    5, 60, 20],
            ['Polo Sparta',             'ropa',       22,   55, 25, 8],
            ['Toalla de entrenamiento', 'accesorio',   8,   20, 30, 10],
        ];

        foreach ($productos as $i => [$nombre, $categoria, $costo, $venta, $stock, $minimo]) {
            Product::updateOrCreate(
                ['gym_id' => $this->gym->id, 'sku' => 'SP-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'name'       => $nombre,
                    'category'   => $categoria,
                    'cost_price' => $costo,
                    'sale_price' => $venta,
                    'stock'      => $stock,
                    'min_stock'  => $minimo,
                    'is_active'  => true,
                ]
            );
        }
    }
}
