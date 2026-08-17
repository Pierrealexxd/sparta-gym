# Ejecutar: Programas + Rutinas Automáticas + Rediseño del Progreso

Lee el plan completo en `PLAN-PROGRAMAS.md` y ejecútalo paso por paso.

## Correcciones al plan ya verificadas contra el código real

El plan tiene errores de API que ya verifiqué leyendo el código. **Aplica estas correcciones, no el código literal del plan:**

1. **`auth()->user()->rol->name === 'cliente'` NO EXISTE.** La relación es `role()` (no `rol`) y se compara por `slug`, no por `name`. El proyecto ya tiene el helper correcto: usa **`auth()->user()->esCliente()`** (`app/Models/User.php:66`). Con el código del plan, el CTA nunca se renderizaría para un cliente.

2. **Ubicación de las vistas.** El plan dice `resources/views/admin/programas/`, pero todo el CRUD de contenido web vive en `resources/views/admin/contenido/{faqs,ejercicios,recetas,testimonios}/`, y la pestaña se agrega en `admin/contenido/_pestanas.blade.php`. Pon las vistas de programas en **`resources/views/admin/contenido/programas/`** por coherencia. Los nombres de ruta (`admin.programas.*`) sí pueden quedarse como dice el plan.

3. **Patrón de formulario del CRUD.** El plan propone páginas separadas `create.blade.php` / `edit.blade.php`. Pero `faqs/index.blade.php` (el patrón que el propio plan manda copiar) usa **un modal en la misma página** disparado con `window.dispatchEvent(new CustomEvent('abrir-faq'))`, con un solo formulario que sirve para crear y editar. Sigue el patrón real de FAQs para Programas. Para las **rutinas base** (formulario grande, con días y ejercicios anidados) sí usa páginas separadas — ahí el modal no da el ancho; copia el patrón de `resources/views/entrenador/rutinas/_form.blade.php`.

Si al implementar encuentras más desajustes entre el plan y el código real, **el código real manda**. Corrige y documenta el desajuste en un comentario breve.

## Reglas

1. **Sin browser, sin QA visual, sin screenshots.** Solo código: PHP, Blade, CSS, JS, migraciones, seeders.
2. **No corromper nada existente.** Antes de tocar un archivo, léelo completo. Edita por reemplazo parcial (`oldString` → `newString`), nunca reescribas archivos existentes enteros.
3. **Responsive first.** Mobile (≤740px) → tablet (741-1024px) → desktop (≥1025px), con los breakpoints que ya existen. Ojo: las tablas del panel pasan a modo tarjeta en **960px** (`panel.css`), no en 640px — cualquier tabla nueva debe llevar la clase `tabla--tarjetas` y sus `<td data-etiqueta="...">` para funcionar en móvil.
4. **Respeta el sistema de diseño.** Todo sale de `tokens.css`. Cero literales de color, tamaño, radio o duración. Si falta un token, agrégalo a `tokens.css`.
5. **Patrones existentes, no inventar.** CRUD de contenido → patrón FAQs. Formulario de rutina con días/ejercicios anidados → patrón del CRUD de rutinas del entrenador. Modales → patrón `modal__fondo`. Confirmaciones de borrado → `$store.confirmar.abrir({...})` como en el resto del panel.
6. **Opina sobre diseño e implementación.** Si algo del plan no funciona bien (visual, seguridad, lógica), cámbialo y justifícalo en un comentario breve en el archivo.
7. **JS mínimo.** Solo Alpine.js, con el patrón `x-data` / `@click` / `$dispatch` ya usado.
8. **Seguridad y permisos.** Las rutas de admin van bajo `permiso:web.editar` (ya existe, no crear permiso nuevo). La ruta de asignación del cliente ya queda protegida por `middleware('rol:cliente')` del grupo de `routes/cliente.php`. Valida que el socio asignado sea el del usuario autenticado — nunca aceptes un `member_id` que venga del request.
9. **Migraciones.** Ejecuta `php artisan migrate` y el seeder de rutinas base. Ojo: `routines` ya existe con datos reales, así que `program_id` debe ser **nullable** para no romper filas existentes.
10. **Verificar en cada paso.** `php -l` en cada `.php` nuevo/modificado, `php artisan view:cache` para validar que los blade compilan, y `npm run build` cuando toques CSS/JS. Reporta si algo falla.
11. **Borrado de comidas: verificado seguro.** Las rutas `progreso.comidas.guardar`, `platos.guardar`, `platos.usar` y `platos.destroy` solo se referencian desde `resources/views/cliente/progreso.blade.php` (el archivo que rediseñas). No hay otras referencias, así que quitarlas es seguro. **No borres** las tablas ni los modelos `SavedMeal`/`MealLog` — el plan pide conservarlos.

## Orden

Sigue la tabla de la sección 10 del plan (pasos 1→22). Agrupados:

```
A. Migraciones + modelos (pasos 1-5)
B. CRUD admin de Programas + pestaña (pasos 6-9)
C. CRUD admin de Rutinas base (pasos 10-12)
D. Asignación automática al cliente (pasos 13-15)
E. Rediseño del progreso (pasos 16-18)
F. Migrar + seedear + verificar (pasos 19-22)
```

Después de cada bloque, corre las verificaciones del punto 10 y reporta el resultado antes de seguir.

## Archivos clave para contexto

- `PLAN-PROGRAMAS.md` — el plan
- `app/Models/Program.php`, `Routine.php`, `RoutineDay.php`, `RoutineExercise.php`, `Member.php`, `User.php`, `Exercise.php`
- `app/Http/Controllers/Admin/FaqController.php` — patrón de CRUD de contenido
- `resources/views/admin/contenido/faqs/index.blade.php` — patrón de vista + modal
- `resources/views/admin/contenido/_pestanas.blade.php` — pestañas de contenido web
- `resources/views/entrenador/rutinas/_form.blade.php` — patrón de formulario con días/ejercicios
- `app/Http/Controllers/Cliente/ProgressController.php` y `resources/views/cliente/progreso.blade.php`
- `resources/views/landing/sections/programa-modal.blade.php` — el CTA a modificar
- `routes/admin.php`, `routes/cliente.php`
- `resources/css/panel.css`, `resources/css/tokens.css`
- `database/seeders/ExerciseSeeder.php` — qué ejercicios existen para el seeder de rutinas base

## Output esperado

Al terminar, entrega:
1. Resumen de archivos creados y modificados (con rutas)
2. Resultado de migraciones, seeders, `php -l`, `view:cache` y `npm run build` por bloque
3. Decisiones que tomaste distintas al plan y por qué
4. Criterios de aceptación de la sección 9 del plan que NO se cumplieron y por qué
5. Tu opinión honesta: qué quedó bien y qué se puede mejorar

No hagas commit ni push. Deja todo en el working tree.
