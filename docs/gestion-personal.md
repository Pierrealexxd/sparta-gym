# Gestión de personal: usuarios, planilla y calendario de entrenadores

> **Estado: Partes E, F y G ✅ completas e implementadas.** Migración de
> planilla corrida, vista de planilla construida, rutas y enlaces del
> menú verificados, `npm run build` y `php artisan view:cache` limpios.
> No queda nada de este documento por ejecutar.

Plan a futuro, sin implementar todavía — para repartir con tu otro agente
mientras seguimos definiendo más cosas. Tres piezas independientes entre
sí (no dependen unas de otras, sí pueden hacerse en paralelo), pero cada
una sí tiene un orden interno que hay que respetar.

Decisiones ya confirmadas contigo:
- El "gestor de gastos" = **planilla/sueldos** que el gimnasio paga a sus
  trabajadores (no gastos que ellos reportan para reembolso).
- El calendario de entrenadores muestra **las rutinas que van creando**
  (dato que ya existe hoy) — no un reporte diario nuevo que tengan que
  llenar aparte.

---

## Parte E — Pantalla "Usuarios" (crear cuentas y asignar rol)

### Por qué

Hoy, según a quién le des acceso, el camino es distinto y a veces no
existe:
- **Entrenador** → se crea automáticamente al registrarlo en
  `/panel/entrenadores` (`TrainerController::store` ya crea el `User` con
  rol fijo `entrenador`). Esto **no cambia**.
- **Recepción** → hoy sólo por `tinker`. No hay pantalla.
- **Cliente (dar acceso a un socio)** → hoy sólo por `tinker`. No hay
  pantalla.

Esta parte agrega la pantalla que falta para los dos últimos casos, sin
tocar cómo se crean los entrenadores (eso ya funciona bien).

### Diseño

- `Admin\UserController` (resource), gateado por el permiso
  `usuarios.gestionar` que ya existe en el seeder pero que nadie usa
  todavía.
- Formulario con: nombre, correo, rol (select de `Role::all()`, sin
  "entrenador" en la lista — ese sigue teniendo su propio flujo), sede
  (si el que crea tiene más de una disponible), estado activo.
- **Caso especial "cliente"**: en vez de un campo de contraseña libre, el
  formulario muestra un buscador de socios (`Member::whereNull('user_id')`
  — sólo los que todavía no tienen cuenta) y al guardar hace
  `$member->update(['user_id' => $user->id])`. Así "darle acceso a un
  socio" es un solo trámite, no crear un usuario suelto y luego ir a
  enlazarlo a mano.
- **Contraseña**: el proyecto no tiene correo configurado para invitaciones
  (coherente con "sin dependencias externas" del resto del proyecto — ver
  `AGENTS.md`, sección Seguridad). La contraseña se genera al azar
  (`Str::password(12)`, igual que ya hace `TrainerController`) y se
  muestra **una sola vez**, en el mensaje de éxito tras crear la cuenta,
  con la instrucción de cambiarla en el primer ingreso. No se guarda en
  ningún sitio visible después de esa pantalla.
- Ruta: `Route::resource('usuarios', UserController::class)->middleware('permiso:usuarios.gestionar')`
  dentro de `routes/admin.php`.
- Enlace "Usuarios" en el menú lateral, grupo "Configuración", visible
  sólo con el permiso.

### Checklist

- [ ] Crear una cuenta de recepción nueva desde el panel → puede iniciar
      sesión y ve exactamente lo que un recepcionista debe ver (nada más)
- [ ] Dar acceso a un socio existente → el socio puede iniciar sesión y
      ve su panel de cliente
- [ ] Un socio que ya tiene cuenta no aparece en el buscador de "dar
      acceso" (para no crear una segunda cuenta por error)
- [ ] La contraseña generada se muestra una sola vez, en ningún lugar
      queda visible después

---

## Parte F — Planilla: pagos a trabajadores

### Por qué

El sistema hoy sólo registra dinero que **entra** (pagos de socios). No
hay forma de registrar lo que **sale** hacia el equipo — sueldos,
comisiones, bonos.

### Diseño

- Tabla nueva `payroll_payments`: `gym_id`, `user_id` (el trabajador que
  cobra), `paid_by` (quién lo registró), `concept` (sueldo/comisión/bono,
  texto libre como en `payments.concept`), `amount`, `method`,
  `period_start`, `period_end`, `paid_at`, `notes`. Usa `BelongsToGym`,
  igual que el resto.
- Modelo `PayrollPayment` — mismo patrón que `Payment` (scopes
  `entreFechas`, `delMes`, etc., se pueden copiar tal cual).
- Permiso nuevo `planilla.gestionar` en el grupo `'Sistema'` del seeder.
- `Admin\PayrollController`: `index` (tabla filtrable por trabajador y
  rango de fechas, con total del rango) y `store` (formulario: elegir
  trabajador de una lista — sólo `User` con rol recepción/entrenador de la
  sede activa —, monto, concepto, período, método).
- Vista nueva `admin/planilla/index.blade.php`, calcada de
  `admin/pagos/index.blade.php` (misma estructura de KPIs + tabla +
  modal), para no inventar un patrón visual nuevo.
- Reporte CSV: mismo patrón que `ReporteController::pagosCsv`, un método
  más (`planillaCsv`) reusando el helper `csv()` que ya existe ahí.
- Enlace "Planilla" en el menú lateral, grupo "Dinero", junto a "Pagos y
  caja".

### Fuera de alcance de esta parte

No calcula automáticamente cuánto le corresponde a cada quien (eso sería
un sistema de nómina con reglas de cálculo, bastante más grande) — es un
registro manual de lo que se le pagó a cada trabajador, para tener
historial y reportes. Si más adelante se necesita cálculo automático de
comisiones por membresías vendidas, por ejemplo, es una parte aparte.

### Checklist

- [ ] Registrar un pago a un entrenador → aparece en su historial
- [ ] Filtrar por trabajador y por rango de fechas
- [ ] El reporte CSV descarga con los mismos campos que se ven en pantalla

---

## Parte G — Calendario de actividad de entrenadores

### Por qué

El dueño quiere ver, de un vistazo mensual, cuándo cada entrenador ha
estado registrando rutinas para sus socios — sin tener que entrar
entrenador por entrenador a revisar.

### Diseño

- **Cero tablas nuevas.** `Routine` ya tiene `trainer_id`, `member_id`,
  `name`, `created_at` — exactamente lo que hace falta. El calendario
  agrupa rutinas por el día en que se crearon.
- `Admin\ActividadController@calendario`: recibe `mes`/`anio` por query
  string (por defecto el mes actual), trae
  `Routine::with(['trainer.user', 'member'])->whereBetween('created_at', [...])->get()->groupBy(fn ($r) => $r->created_at->toDateString())`.
- Vista `admin/actividad/calendario.blade.php`: una cuadrícula de 7
  columnas (como cualquier calendario), construida a mano en Blade — sin
  librería de calendario nueva, coherente con "sin dependencias externas"
  del resto del proyecto. Cada celda de día con actividad muestra un
  contador pequeño (p. ej. "3 rutinas"); Alpine controla qué día está
  "abierto".
- Al hacer clic en un día con actividad, un modal (mismo patrón
  `.modal__fondo` / `.modal__caja` que ya se usa en Pagos y en la ficha
  del socio) lista las rutinas de ese día: entrenador, socio, nombre de
  la rutina, hora de registro.
- Navegación mes anterior / mes siguiente con los mismos query params.
- Enlace "Actividad" en el menú lateral, grupo "Socios" o uno nuevo
  "Entrenamiento" (a decidir cuando se implemente, según cómo vaya
  creciendo el menú).

### Checklist

- [ ] El calendario muestra el mes actual con los días marcados donde
      hubo rutinas creadas
- [ ] Clic en un día abre el modal con el detalle correcto (probar un día
      con una rutina y un día con varias, de distintos entrenadores)
- [ ] Navegar a un mes sin actividad no rompe nada — se ve vacío, no error
- [ ] Cambiar de mes conserva la sede activa (si el dueño está viendo
      "todas las sedes", el calendario debería reflejar eso — decidir si
      agrupa por sede o las mezcla; **pendiente de definir cuando se
      implemente**, anotado aquí para no perderlo)

---

## Orden sugerido de ejecución

Las tres partes son independientes — se pueden repartir en paralelo. Si
tu otro agente sólo puede tomar una a la vez, este es el orden de mayor
a menor impacto inmediato:

1. **Parte E** (Usuarios) — es la que más se conecta con lo que ya
   preguntaste hoy (roles y accesos), y desbloquea que puedas dar de alta
   a tu equipo real sin depender de comandos.
2. **Parte G** (Calendario) — no toca dinero ni permisos nuevos, es la
   más segura de probar sin riesgo.
3. **Parte F** (Planilla) — toca dinero saliente, conviene que la revises
   tú antes de darla por buena, igual que con Pagos y Cierre de caja.

## Fuera de alcance de todo este documento

- Cálculo automático de comisiones o sueldos variables (Parte F es
  registro manual, no motor de cálculo).
- Reporte diario que el entrenador llena a mano (se descartó a favor de
  reusar las rutinas ya existentes, Parte G).
- Notificaciones o recordatorios (por correo o similar) — el proyecto no
  tiene mailer configurado hoy; añadirlo sería una decisión aparte.
