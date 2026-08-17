# Ejecutar: Módulo de Ventas + Clientes/Membresías

Lee el plan completo en `PLAN-VENTAS-CLIENTES.md` y ejecútalo parte por parte.

## Reglas

1. **Sin browser, sin QA, sin screenshot.** Solo código: PHP, Blade, CSS, JS, migraciones, seeders, dependencias de composer.
2. **No corromper nada existente.** Antes de tocar un archivo, léelo completo para entender su contexto. Edita siempre por reemplazo parcial (`oldString` → `newString`), nunca reescribas archivos enteros salvo que sean nuevos.
3. **Responsive first.** Para cada componente nuevo, diseña primero la versión mobile (≤740px), luego tablet (741-1024px), luego desktop (≥1025px). Usa los breakpoints que ya existen en el proyecto.
4. **Respeta el sistema de diseño.** Todo sale de `tokens.css`. Ni un solo color, tamaño, border-radius o duración literal en CSS (excepción justificada: el verde de marca de WhatsApp #25D366, que el propio plan especifica como color de marca externo — documentarlo en comentario si se usa literal). Si necesitas un token que no existe, agrégalo a `tokens.css`.
5. **Patrones existentes, no inventar.** Copia el patrón de modal de venta de `admin/ventas/index.blade.php`, el patrón `modal__fondo` para el modal de importación, y reusa el icono `descargar` ya existente en el sprite para exportar. Si algo ya está hecho, reúsalo.
6. **Opina sobre diseño e implementación.** Si ves que algo del plan no funciona bien (visual, de seguridad, o de lógica), cambia el plan. Toma decisiones con gusto — pero justifícalas en un comentario breve en el archivo.
7. **Iconos SVG.** Crea iconos simples y limpios. El estilo actual usa trazos finos (1.5-2px), sin relleno, viewBox 24x24. Sigue ese estilo. Solo crea `subir` y `whatsapp` — no reinventes iconos que ya existen (`descargar`, `agregar`, `papelera`, `lapiz`).
8. **JS mínimo.** Solo Alpine.js para interactividad, siguiendo el patrón `x-data`/`@click`/`$dispatch` ya usado en el modal de venta.
9. **No tocar lógica de negocio existente ni el wizard de matrícula.** La Parte 4 es de *verificación*: si encuentras un bug real, corrígelo con el mínimo cambio posible y documenta qué encontraste y cómo lo arreglaste. No refactorices código que ya funciona.
10. **Seguridad de la importación.** Valida el archivo (tipo, tamaño, columnas) antes de procesar filas. Usa transacciones como indica el plan. No confíes en datos del Excel sin sanitizar antes de usarlos en queries o creación de modelos.
11. **Probar al final de cada paso.** Después de cada paso del plan, ejecuta `npm run build` (para los cambios de CSS/JS) y verifica sintaxis PHP (`php -l`) de los archivos nuevos/modificados. Para dependencias de composer, confirma que `composer require` termina sin error y que `php artisan serve`/autoload no se rompe.

## Orden

Ejecuta en este orden exacto (igual a la sección 10 del plan):

```
Paso 0 → Instalar dependencias (dompdf, maatwebsite/excel) + publicar config
Paso 1 → Exportar ventas (PDF + Excel)
Paso 2 → Importar ventas desde Excel
Paso 3 → Botón WhatsApp de membresía por vencer
Paso 4 → Verificación de registro de ventas por empleados (checklist sección 6.2 del plan)
Paso 5 → QA final de código (build, sin browser)
```

Después de cada paso, haz un `npm run build` (cuando aplique) y reporta si compiló sin errores.

## Archivos clave para contexto

- `resources/css/tokens.css` — identidad del proyecto
- `resources/css/panel.css` — estilos del panel admin
- `resources/views/admin/ventas/index.blade.php` — vista de ventas, patrón de modal y KPIs
- `app/Http/Controllers/Admin/SaleController.php` — controlador de ventas
- `resources/views/admin/clientes/show.blade.php` — detalle de cliente, pestaña membresías
- `app/Http/Controllers/Admin/MemberController.php` — controlador de detalle de cliente
- `app/Http/Controllers/Admin/MembershipController.php` — registro/renovación de membresías
- `app/Http/Controllers/Admin/MatriculaController.php` — wizard de matrícula (NO TOCAR lógica)
- `app/Services/MatriculaService.php` — lógica de registrarVenta/renovarMembresia
- `app/Models/Sale.php`, `app/Models/SaleItem.php`, `app/Models/StockMovement.php`, `app/Models/Product.php`
- `routes/admin.php` — rutas del panel admin
- `config/sparta.php` — métodos de pago, umbral de aviso de vencimiento
- `database/seeders/RolePermissionSeeder.php` — confirma que `reportes.exportar` existe y a quién está asignado

## Output esperado

Al terminar los 5 pasos, entrega:
1. Resumen de todos los archivos creados y modificados
2. Resultado de `composer require` y `npm run build` por paso, con errores si los hubo
3. Resultado del checklist de verificación de ventas (sección 6.2 del plan) — qué funcionaba, qué no, qué corregiste
4. Tu opinión honesta: qué quedó bien, qué se podría mejorar, qué decisiones tomaste y por qué
