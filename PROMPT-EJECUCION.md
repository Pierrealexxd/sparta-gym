# Ejecutar: Rutinas Personalizadas + Mejoras Panel

Lee el plan completo en `PLAN-RUTINAS-PERSONALIZADAS.md` y ejecútalo fase por fase.

## Reglas

1. **Sin browser, sin QA, sin screenshot.** Solo código: PHP, Blade, CSS, JS, migraciones, seeders.
2. **No corromper nada existente.** Antes de tocar un archivo, léelo completo para entender su contexto. Edita siempre por reemplazo parcial (`oldString` → `newString`), nunca reescribas archivos enteros salvo que sean nuevos.
3. **Responsive first.** Para cada componente nuevo, diseña primero la versión mobile (≤740px), luego tablet (741-1024px), luego desktop (≥1025px). Usa los breakpoints que ya existen en el proyecto.
4. **Respeta el sistema de diseño.** Todo sale de `tokens.css`. Ni un solo color, tamaño, border-radius o duración literal en CSS. Si necesitas un token que no existe, agrégalo a `tokens.css`.
5. **Patrones existentes, no inventar.** Copia el patrón de modal-info de `beneficios.blade.php`, el patrón de tarjetas de `ejercicios.blade.php`, el patrón de Alpine.js de las secciones existentes. Si algo ya está hecho, reúsalo.
6. **Opina sobre diseño.** Si ves que algo del plan no funciona bien visualmente o se puede mejorar, cambia el plan. Toma decisiones de diseño con gusto — pero justifícalas en un comentario breve en el archivo.
7. **Iconos SVG.** Crea iconos simples y limpios. El estilo actual usa trazos finos (1.5-2px), sin relleno, viewBox 24x24. Sigue ese estilo.
8. **JS mínimo.** Solo Alpine.js para interactividad. GSAP solo si la animación ya tiene soporte en `animations.js`. Chart.js ya está en el proyecto para gráficas.
9. **Probar al final.** Después de cada fase, ejecuta `npm run build` para verificar que no hay errores de compilación.

## Orden

Ejecuta en este orden exacto:

```
Fase 0 → Migración + Modelo + Seeder + Iconos SVG
Fase 1 → Sección programas en landing (solo las tarjetas, sin modal)
Fase 2 → Modal de detalle de programa
Fase 3 → NutritionAdvisor + Recomendaciones en perfil + Modal guía
Fase 4 → Mejoras visuales en panel de progreso
Fase 5 → Reseñas (mejoras menores)
```

Después de cada fase, haz un `npm run build` y reporta si compiló sin errores.

## Archivos clave para contexto

- `resources/css/tokens.css` — identidad del proyecto
- `resources/css/landing.css` — estilos de la landing
- `resources/css/panel.css` — estilos del panel
- `resources/views/landing/index.blade.php` — estructura de secciones
- `resources/views/landing/sections/ejercicios.blade.php` — patrón de tarjetas + modal video
- `resources/views/landing/sections/guias.blade.php` — patrón modal-info
- `resources/views/landing/sections/testimonios.blade.php` — patrón carrusel
- `resources/views/layouts/public.blade.php` — layout de la landing
- `resources/views/perfil/index.blade.php` — perfil del usuario
- `resources/views/cliente/progreso.blade.php` — panel de progreso
- `resources/views/cliente/dashboard.blade.php` — dashboard del cliente
- `app/Http/Controllers/LandingController.php` — datos de la landing
- `app/Http/Controllers/PerfilController.php` — controlador de perfil
- `app/Http/Controllers/Cliente/ProgressController.php` — controlador de progreso
- `app/Models/Member.php` — modelo con métodos de nutrición
- `app/Models/Recipe.php` — recetas compartidas
- `resources/js/animations.js` — animaciones GSAP

## Output esperado

Al terminar las 5 fases, entrega:
1. Resumen de todos los archivos creados y modificados
2. Errores de build si los hubo
3. Tu opinión honesta: qué quedó bien, qué se podría mejorar, qué decisiones tomaste y por qué
