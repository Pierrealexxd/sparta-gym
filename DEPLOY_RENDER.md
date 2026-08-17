# Despliegue gratis de Sparta Gym en Render + Aiven

Objetivo: dejar la app viva en internet a **coste $0/mes** para que el dueño la vea
(landing + login + panel de administrador con datos demo).

> Si el deploy falla, revisar primero [DEPLOY_INCIDENTS.md](DEPLOY_INCIDENTS.md) —
> bitácora de problemas ya diagnosticados en producción (p. ej. Aiven apagándose
> por inactividad) antes de investigar desde cero.

## Por qué esta combinación

- **Render** (free web service, Docker): hosting PHP/Laravel gratis. La app y el
  proceso PHP viven aquí.
- **Aiven for MySQL** (free tier): Render **no ofrece MySQL** gratis (su Postgres
  gratis expira a los 30 días y además el proyecto **requiere MySQL**: usa columnas
  generadas, p.ej. `attendances.attended_on`). Aiven da un MySQL real, siempre
  gratis, sin tarjeta: 1 CPU, 1 GB RAM, 1 GB disco, conexiones SSL.
- No hace falta Redis ni colas externas: `SESSION_DRIVER`, `QUEUE_CONNECTION` y
  `CACHE_STORE` ya apuntan a la base de datos.

### Limitaciones honestas del free (aceptables para demo)

| Limitación | Efecto |
|---|---|
| Render free duerme a los 15 min sin tráfico | La primera visita tras rato inactivo tarda ~30-60 s (cold start). Para que no pase, un monitor gratuito tipo **UptimeRobot** (cada 5 min) lo mantiene despierto. Entra holgado en las 750 h/mes gratis. |
| Disco efímero en free | Cualquier archivo subido a `storage/app/public` se pierde al redeployar. Las imágenes del sitio viven en `public/` (parte de la imagen Docker), así que no se pierden. |
| Aiven free se apaga tras inactividad prolongada | Avisa por correo; se enciende de nuevo en un clic. |
| 1 GB de disco en Aiven | Suficiente para el dataset demo (~36 000 asistencias). |
| El formulario de contacto no envía correo | `MAIL_MAILER=log`. Para demo está bien. |

## Fases

### Fase 0 — Cuentas (HUMANO, ~10 min)

1. Crear cuenta en **Render** (render.com) — permite login con GitHub.
2. Crear cuenta en **Aiven** (aiven.io) — sin tarjeta, login con GitHub/Google.
3. En Aiven: **Create service → Aiven for MySQL → plan Free**. Anotar de la pestaña
   *Overview*: `host`, `port`, `dbname` (suele ser `defaultdb`), `user` (suele ser
   `avnadmin`) y `password`. Descargar también `ca.pem` (el botón está en la misma
   pantalla) y guardarlo en `storage/certs/aiven-ca.pem` dentro del repo.
4. Tener un repo en **GitHub** (el agente lo puede crear con `gh repo create` si
   `gh` está autenticado; si no, crearlo a mano y darle permiso al agente).

### Fase 1 — Preparar el repo (AGENTE)

1. **Primer commit del proyecto** (hoy no hay ninguno: `git log` está vacío).
   - Cuidado con el tamaño: la carpeta `IMAGENES/` (~8 MB, material fuente duplicado)
     no hace falta en GitHub. Añadirla a `.gitignore` y quitarla del índice
     (`git rm -r --cached IMAGENES`) **antes** del primer commit.
2. Crear los 4 ficheros de despliegue de abajo (secciones «Ficheros a crear»).
3. Configurar credenciales de despliegue **sin tocar el `.env` real**:
   - Generar la key: `php artisan key:generate --show` → copiar el valor.
   - El `.env` de producción **no se commitea nunca** (ya está en `.gitignore`).
     Las variables se pasan como variables de entorno en Render, ver Fase 3.
4. Commit + push a GitHub (`main`).

### Fase 2 — Poblar la base de datos (AGENTE, desde local)

El esquema y los datos demo se crean **una sola vez** apuntando a Aiven, desde el
terminal local (así no se complica el arranque del contenedor):

```powershell
# Primero guardar ca.pem en storage/certs/aiven-ca.pem (Fase 0.3)
$env:DB_CONNECTION="mysql"
$env:DB_HOST="<host-aiven>"
$env:DB_PORT="<puerto-aiven>"
$env:DB_DATABASE="defaultdb"
$env:DB_USERNAME="avnadmin"
$env:DB_PASSWORD="<password>"
$env:MYSQL_ATTR_SSL_CA="storage/certs/aiven-ca.pem"
php artisan migrate --force
php artisan db:seed --force
# Limpiar las variables de entorno al terminar
Remove-Item Env:DB_CONNECTION,Env:DB_HOST,Env:DB_PORT,Env:DB_DATABASE,Env:DB_USERNAME,Env:DB_PASSWORD,Env:MYSQL_ATTR_SSL_CA
```

- Las migraciones usan columnas generadas de MySQL → deben correr contra Aiven
  (MySQL 8), nunca contra SQLite.
- El seeder ya genera el gimnasio, usuarios demo (ver AGENTS.md) y ~36 000
  asistencias. Tarda ~30-60 s.

### Fase 3 — Crear el servicio web en Render (HUMANO con clicks, ~5 min)

1. **New → Web Service → connect GitHub repo** (rama `main`).
2. Runtime: **Docker** (usa el `Dockerfile` del repo).
3. Instance type: **Free**.
4. Variables de entorno (marcar como `Secret` las sensibles):

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<valor generado en Fase 1.3>
APP_NAME=Sparta Gym
APP_URL=https://<nombre-del-servicio>.onrender.com   # el que Render asigne
APP_LOCALE=es
DB_CONNECTION=mysql
DB_HOST=<host-aiven>
DB_PORT=<puerto-aiven>
DB_DATABASE=defaultdb
DB_USERNAME=avnadmin
DB_PASSWORD=<password>
MYSQL_ATTR_SSL_CA=/app/certs/aiven-ca.pem
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
LOG_CHANNEL=stack
LOG_LEVEL=error
PHP_CLI_SERVER_WORKERS=4
```

   El `config/database.php` ya soporta `MYSQL_ATTR_SSL_CA` (env), no hay que tocar
   código para el SSL de Aiven.
5. Deploy. Esperar a que el build termine (primera vez tarda unos minutos).
6. **Opcional y recomendado para la demo:** crear monitor gratis en UptimeRobot
   contra `https://<servicio>.onrender.com` (intervalo 5 min) para que el servicio
   no se duerma y el dueño no vea el cold start.

### Fase 4 — Verificar (AGENTE)

- `GET /` → landing con contenido de BD.
- Login `admin@spartagym.pe` / `sparta2026` → `/panel` con el dashboard con datos.
- Login `recepcion@spartagym.pe` → panel de recepción.
- Comprobar que los assets cargan (build de Vite) y que las URLs usan HTTPS
  (Laravel 12 ya confía en los proxies de Render).
- Verificar `/mi-cuenta` con un socio que tenga `user_id` (el seeder los crea).
- Si algo rompe en producción: `APP_DEBUG` solo en true temporalmente para
  diagnosticar, nunca dejarlo activo.

## Ficheros a crear

### `Dockerfile`

```dockerfile
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress

FROM php:8.3-cli-alpine
WORKDIR /app
RUN apk add --no-cache icu-dev \
    && docker-php-ext-install pdo_mysql mbstring intl \
    && docker-php-ext-enable opcache
COPY --from=deps /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/certs/aiven-ca.pem /app/certs/aiven-ca.pem
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && mkdir -p storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache
EXPOSE 8000
ENV PHP_CLI_SERVER_WORKERS=4
ENTRYPOINT ["/entrypoint.sh"]
```

### `entrypoint.sh`

```sh
#!/bin/sh
set -e
php artisan storage:link || true
php artisan migrate --force
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
```

### `.dockerignore`

```
.git
.env
.env.backup
.vscode
.idea
node_modules
vendor
/public/build
/public/hot
/public/storage
/storage/*.key
/storage/logs/*
IMAGENES
graphify-out
```

### `.gitignore` (añadir, para no subir material fuente)

```
/IMAGENES
```

## Notas de seguridad

- Nunca commitear `.env` ni contraseñas. Las credenciales de Aiven van como
  *Secret* en Render.
- `APP_DEBUG=false` en producción.
- El login ya no revela qué correos existen (ver AGENTS.md) y la app regenera la
  sesión al autenticar — comportamiento correcto en producción sin cambios.
- Si el repo se hace público, no exponer el `ca.pem` como problema no lo es
  (es un certificado público), pero las credenciales de BD sí.

## Optimización de rendimiento (Front-end)

### Problema original

Un solo `app.js` cargaba todo el sitio: Chart.js (~120 KB), GSAP (~40 KB),
jsQR (~10 KB), Alpine.js, y todo el CSS (101 KB) — sin importar si la página
lo necesitaba. Un visitante de la landing bajaba gráficos del panel; un admin
en el dashboard bajaba animaciones de la landing.

### Solución aplicada

**CSS split por contexto** — 3 entry points en `vite.config.js`:

| Entry point | Archivo | Página | CSS que incluye |
|---|---|---|---|
| `panel-entry.css` | `resources/css/panel-entry.css` | `/panel/*` | tokens + base + components + panel |
| `public-entry.css` | `resources/css/public-entry.css` | Landing, `/`, `/sedes` | tokens + base + components + landing |
| `auth-entry.css` | `resources/css/auth-entry.css` | `/login`, `/registro` | tokens + base + components + auth |

Cada layout Blade carga solo su CSS via `@vite`:
- `layouts/panel.blade.php` → `panel-entry.css` + `app-panel.js`
- `layouts/public.blade.php` → `public-entry.css` + `app-public.js`
- `auth/login.blade.php`, `auth/registro.blade.php` → `auth-entry.css` + `app-public.js`

**JS dinámico** — módulos pesados con `import()` bajo demanda:

| Módulo | Peso | Se carga cuando... |
|---|---|---|
| Chart.js (graficos) | ~184 KB | hay `[data-grafico]` (dashboard, progreso) |
| GSAP + ScrollTrigger (animations) | ~116 KB | hay `[data-revelar]` (animaciones landing) |
| jsQR (escáner) | ~131 KB | el entrenador abre el escáner QR |
| QR generator (qrcode) | ~26 KB | se muestra un carnet o código QR |
| interacciones | ~0.8 KB | hay `.tarjeta--interactiva` |

**Resultado:**

| Página | JS antes | JS ahora (carga inmediata) | Ahorro |
|---|---|---|---|
| Panel (admin/entrenador/cliente) | ~565 KB | ~111 KB | **80%** |
| Landing pública | ~222 KB | ~106 KB | **52%** |
| Login / Registro | ~222 KB | ~106 KB | **52%** |

### Regla para mantener la optimización

Al añadir un módulo JS nuevo al panel:
1. Si pesa > 5 KB → importarlo con `import()` dinámico, no estático.
2. Los componentes Alpine que registran vía `alpine:init` (como `escaneo-qr.js`)
   **deben** ser import estático porque necesitan estar listos antes de
   `Alpine.start()`. Pero sus dependencias pesadas (como `jsQR`) van dinámicas
   dentro del componente.

---

## Resetear la base de datos de Aiven (producción)

### Cuándo hacerlo

- Después de un `migrate:fresh --seed` local que deja la DB en un estado
  distinto al de producción.
- Cuando se quiera limpiar datos demo de producción (socios, asistencias, pagos).
- Cuando se quiera empezar de cero con el admin como único usuario.

### Opción A: Limpieza parcial (mantener contenido web)

Borra solo datos operativos (ventas, asistencia, inventario, clientes) y deja
intactos planes, ejercicios, FAQs, testimonials, galería, roles y permisos.

**Desde la máquina local** (necesita las credenciales de Aiven):

```php
<?php
// clean_aiven_partial.php — ejecutar con: php clean_aiven_partial.php
$host = '<host-aiven>';
$port = '<puerto-aiven>';
$db   = 'defaultdb';
$user = 'avnadmin';
$pass = '<password>';
$ca   = __DIR__ . '/docker/certs/aiven-ca.pem';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_SSL_CA => $ca,
]);

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

$tablas = [
    // Ventas / pagos
    'sale_items', 'sales', 'payments', 'cash_closings', 'payroll_payments', 'memberships',
    // Clientes
    'member_measurements', 'member_goals', 'meal_log_items', 'meal_logs',
    'saved_meal_items', 'saved_meals',
    // Asistencia
    'attendance_edit_requests', 'staff_attendances', 'attendances',
    // Entrenamiento
    'routine_exercises', 'routine_days', 'routines', 'trainer_assignments',
    // Inventario
    'stock_movements', 'products',
    // Mensajería
    'messages', 'conversation_participants', 'conversations',
    'notifications', 'sessions',
];

foreach ($tablas as $t) {
    try { $pdo->exec("DELETE FROM `$t`"); echo "$t: OK\n"; }
    catch (PDOException $e) {
        if (str_contains($e->getMessage(), "doesn't exist")) echo "$t: SKIP\n";
        else throw $e;
    }
}

$pdo->exec('DELETE FROM trainers WHERE user_id != 1');
$pdo->exec('DELETE FROM users WHERE id != 1');
$pdo->exec('TRUNCATE TABLE members');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "\n--- Verificación ---\n";
foreach (['users','members','payments','sales','plans','faqs'] as $t) {
    $r = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo "$t: $r\n";
}
```

> **Nota:** Algunas tablas nuevas (`stock_alerts`, `notifications`) pueden no
> existir en producción si aún no se ha hecho deploy con esas migraciones.
> El script las ignora automáticamente.

### Opción B: Reset completo (desde consola de Aiven)

1. Entrar a [console.aiven.io](https://console.aiven.io)
2. Seleccionar el servicio MySQL → pestaña **"Console"**
3. Pegar y ejecutar:

```sql
SET FOREIGN_KEY_CHECKS = 0;

-- Ventas / pagos
TRUNCATE TABLE sale_items;
TRUNCATE TABLE sales;
TRUNCATE TABLE payments;
TRUNCATE TABLE cash_closings;
TRUNCATE TABLE payroll_payments;
TRUNCATE TABLE memberships;

-- Clientes
TRUNCATE TABLE member_measurements;
TRUNCATE TABLE member_goals;
TRUNCATE TABLE meal_log_items;
TRUNCATE TABLE meal_logs;
TRUNCATE TABLE saved_meal_items;
TRUNCATE TABLE saved_meals;

-- Asistencia
TRUNCATE TABLE attendance_edit_requests;
TRUNCATE TABLE staff_attendances;
TRUNCATE TABLE attendances;

-- Entrenamiento
TRUNCATE TABLE routine_exercises;
TRUNCATE TABLE routine_days;
TRUNCATE TABLE routines;
TRUNCATE TABLE trainer_assignments;
DELETE FROM trainers WHERE user_id != 1;

-- Inventario
TRUNCATE TABLE stock_movements;
TRUNCATE TABLE products;

-- Mensajería / notificaciones
TRUNCATE TABLE messages;
TRUNCATE TABLE conversation_participants;
TRUNCATE TABLE conversations;
TRUNCATE TABLE notifications;
TRUNCATE TABLE sessions;

-- Usuarios (solo admin)
DELETE FROM users WHERE id != 1;
TRUNCATE TABLE members;

SET FOREIGN_KEY_CHECKS = 1;
```

### Opción C: Reset completo con datos demo (desde local)

```powershell
$env:DB_HOST="<host-aiven>"
$env:DB_PORT="<puerto-aiven>"
$env:DB_DATABASE="defaultdb"
$env:DB_USERNAME="avnadmin"
$env:DB_PASSWORD="<password>"
$env:MYSQL_ATTR_SSL_CA="docker/certs/aiven-ca.pem"
php artisan migrate:fresh --seed --force
```

### Verificar que funcionó

```powershell
# Verificar que solo queda el admin
php artisan tinker --execute="echo App\Models\User::count() . ' users, ' . App\Models\Member::count() . ' members';"
# Debería decir: 1 users, 0 members
```

O desde la consola de Aiven:

```sql
SELECT 'users' AS tbl, COUNT(*) AS cnt FROM users
UNION ALL SELECT 'members', COUNT(*) FROM members
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'plans', COUNT(*) FROM plans;
-- Debería dar: users=1, members=0, payments=0, plans=4
```

---

## Siguientes pasos cuando se quiera «de verdad»

1. **Aiven**: subir de free a plan de pago (guardar backups, no se apaga).
2. **Render**: plan Individual ($7/mes) elimina el spin-down y permite disco
   persistente para subidas.
3. Dominio propio y `SESSION_DOMAIN`.
4. Correo real (`MAIL_MAILER` con SMTP).
5. Cuando el panel de administrador esté completo y haya que servir en serio,
   reemplazar `php artisan serve` por nginx + PHP-FPM (misma imagen, solo cambia
   el entrypoint).
