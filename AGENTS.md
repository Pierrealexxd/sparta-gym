# sparta-gym — AGENTS.md

Plataforma de gestión para Sparta Gym. Laravel 12 · Blade · CSS propio · Alpine · GSAP · Vite · MySQL.

Diseñada como SaaS multi-gimnasio desde el primer día, aunque hoy sirva a uno solo.

## Arrancar

```bash
php artisan serve
npm run dev
```

Base de datos: MySQL `sparta_gym` (XAMPP). Para reconstruirla con datos de demo:

```bash
php artisan migrate:fresh --seed
```

Tarda ~30 s: genera 120 socios con años de historial, ~36 000 asistencias y
~1 000 pagos, para que el dashboard tenga series reales que graficar.

### Cuentas de demo

| Rol | Correo | Contraseña |
|-----|--------|-----------|
| Administrador | `admin@spartagym.pe` | `sparta2026` |
| Recepción | `recepcion@spartagym.pe` | `sparta2026` |
| Entrenador | `kevin@spartagym.pe` | `sparta2026` |
| Cliente | cualquier socio con `user_id` | `sparta2026` |

## Estado

| Parte | Estado |
|-------|--------|
| Esquema de BD, modelos, relaciones, seeders | Completo |
| Roles y permisos | Completo |
| Autenticación | Completo |
| Landing pública | Completa |
| Panel de administrador | **Pendiente** |
| Paneles de entrenador y cliente | **Pendiente** |
| Asistencia por QR, reportes PDF/Excel, inventario | **Pendiente** (el esquema ya los soporta) |

Las rutas `/panel`, `/entrenador` y `/mi-cuenta` existen y validan el rol, pero
muestran un marcador de posición.

## Multi-gimnasio: la regla que no se rompe

`App\Models\Concerns\BelongsToGym` aplica un *global scope* que filtra por
`gym_id` y lo rellena solo al crear. **Cualquier modelo con datos de un
gimnasio debe usar ese trait.** Así, olvidar un `where('gym_id', ...)` deja de
ser una fuga de datos posible.

- El gimnasio activo lo resuelve `App\Support\GymContext`, hoy por
  `config('sparta.gym_slug')`. **Ese es el único punto que cambia** el día que
  se resuelva por subdominio o por usuario.
- Para cruzar gimnasios a propósito: `Modelo::sinFiltroDeGimnasio()`. Es
  explícito porque saltarse el aislamiento debe verse en el código.
- `Gym` y `Exercise` **no** usan el trait: `Gym` es la raíz del aislamiento, y
  un ejercicio con `gym_id` nulo pertenece a la biblioteca compartida (ver
  `Exercise::disponibles()`).

## Decisiones de esquema que conviene respetar

- **Los importes se congelan.** `memberships.price`, `memberships.plan_name` y
  `sale_items.product_name` se copian en el momento de la venta. Si mañana sube
  el plan, el histórico no puede reescribirse solo.
- **El IMC no se almacena**, se calcula (`MemberMeasurement::getBmiAttribute`).
  Guardarlo abriría la puerta a que quede desincronizado del peso que lo produce.
- **`payments.paid_at` ≠ `created_at`.** Recepción registra en diferido y la
  caja tiene que cuadrar por fecha real de cobro, no de captura.
- **`attendances.attended_on`** es una columna generada por MySQL. Agrupar por
  día es la consulta más frecuente y `DATE(checked_in_at)` impide usar índice.
  No escribirla desde PHP.
- **`products.stock` es un saldo**; la verdad está en `stock_movements`.
- **Los socios se separan de los usuarios.** No todo socio tiene cuenta, y el
  expediente físico no pertenece a la identidad de acceso.

## Permisos

RBAC propio y pequeño (sin paquetes): `roles`, `permissions`, `permission_role`
y `users.role_id`. Se comprueba con `$user->tienePermiso('pagos.registrar')`.

- El **administrador no se enumera**: puede todo por definición. No lo añadas a
  la lista de asignaciones del seeder.
- Los permisos por rol van cacheados una hora y `Role` invalida su caché sola
  al guardarse.
- Añadir un permiso = una línea en `RolePermissionSeeder::PERMISOS` y volver a
  lanzarlo. Es idempotente.

## Frontend

**Sin Tailwind, a propósito.** La identidad es un sistema de tokens propio; un
framework de utilidades significaría dos resets y dos escalas compitiendo.

```
resources/css/
  tokens.css      ← LA identidad. Ningún componente escribe un color a mano.
  base.css        ← reset, tipografía, utilidades estructurales
  components.css  ← botones, cristal, tarjetas, riel, discos, formularios
  landing.css     ← secciones de la web pública

resources/js/
  app.js          ← arranque
  ui.js           ← menú, acordeón, cabecera (funciona sin GSAP)
  animations.js   ← GSAP + ScrollTrigger
```

### Dirección visual

Derivada del logotipo, no de un moodboard:

- **Paleta:** obsidiana / grafito / acero / **sangre** / **brasa** / hueso / bronce.
  El rojo nunca va solo sobre negro —eso es un neón—; va con brasa y bronce,
  que es lo que lo convierte en fuego. Está en el logo.
- **Tipografía:** *Big Shoulders Display* (titulares), *Sora* (texto),
  *JetBrains Mono* (cifras y etiquetas). Se evita Bebas/Oswald a propósito:
  es el default de todo gimnasio.
- **Elemento firma:** el **riel moleteado** (`.riel`), el grafilado de una barra
  olímpica convertido en indicador de scroll, fijo a la izquierda. Se dibuja con
  gradientes repetidos, sin imágenes.
- **Las cifras se representan con pilas de discos** (`<x-discos>`), no con barras
  de progreso: el dato se lee contando hierro.
- **Copy lacónico.** Los espartanos eran célebres por su brevedad —de ahí viene
  la palabra—. Frases cortas, sin relleno. Mantener ese registro al añadir texto.

### Reglas al tocar el frontend

- Nunca escribir un color, tamaño o duración literal: todo sale de `tokens.css`.
- El estado inicial de los revelados vive en CSS (`[data-revelar]`), no en JS,
  para que no haya parpadeo antes de que GSAP arranque.
- `prefers-reduced-motion` no puede dejar la página en blanco: `animations.js`
  deshace explícitamente el estado inicial cuando está activo.
- Tras cambiar CSS o JS hay que reconstruir (`npm run build`) o tener
  `npm run dev` corriendo.

## Contenido de la web

Todo lo que se ve en la landing sale de la base de datos, no de texto incrustado
en Blade: `plans`, `trainers`, `facilities`, `faqs`, `testimonials`,
`gallery_images`. El administrador podrá editarlo desde el panel sin tocar código.

Las cifras del hero se calculan de datos reales y se cachean una hora
(`LandingController::cifras`).

## Seguridad

- Contraseñas con el cast `hashed`; nunca `bcrypt()` a mano.
- El login comprueba `is_active` dentro de las credenciales y devuelve **el
  mismo mensaje** para cuenta inexistente, contraseña incorrecta y cuenta
  desactivada: no revelar qué correos existen.
- `session()->regenerate()` tras autenticar, contra fijación de sesión.
- Formulario de contacto: `throttle:6,1` más un campo trampa (`website`) que
  debe llegar vacío. Sin captcha ni dependencias externas.
- Todos los formularios llevan `@csrf`.

## gstack (recomendado)

Este proyecto usa [gstack](https://github.com/garrytan/gstack) para los flujos de
trabajo asistidos por IA. Ya está instalado globalmente en
`~/.agents/skills/gstack` (y `~/.claude/skills/gstack`); los skills quedan
disponibles en esta sesión sin instalar nada más en el repo.

Skills como `/qa`, `/ship`, `/review`, `/investigate` y `/browse` se usan como
checkpoint de calidad antes de dar algo por terminado. Usar `/browse` para todo
lo que implique abrir una web, y las rutas `~/.agents/skills/gstack/...` como
referencia de archivos de gstack.

Para actualizar gstack a la última versión:

```bash
git -C ~/.agents/skills/gstack pull
```

Los binarios (p. ej. `browse/dist/browse`) se reconstruyen con
`cd ~/.agents/skills/gstack && ./setup` si quedaran obsoletos tras el pull.
