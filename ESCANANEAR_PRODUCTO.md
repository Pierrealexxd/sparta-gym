# Integración de escaneo de código de barras en Inventario

El plan detalla la implementación de la lectura de códigos de barras mediante la cámara del dispositivo para buscar o pre-llenar productos en el modal de "Nuevo producto", respetando los requisitos técnicos y estéticos del proyecto Sparta Gym.

## User Review Required

El plan sigue exactamente la especificación solicitada. Por favor, revísalo y confirma si estás de acuerdo para proceder con la ejecución.

## Proposed Changes

### Database

#### [NEW] `database/migrations/XXXX_XX_XX_XXXXXX_agregar_barcode_a_products.php`
- Se creará una migración para agregar la columna `barcode` (`string`, `nullable`) a la tabla `products`.
- Se añadirá una restricción de unicidad compuesta entre `gym_id` y `barcode` para respetar el aislamiento multi-gimnasio, pero permitiendo nulos.

### Backend

#### [MODIFY] `app/Models/Product.php`
- Se añadirá `'barcode'` al array `$fillable` para permitir asignación masiva.

#### [MODIFY] `app/Http/Controllers/Admin/ProductController.php`
- Se añadirá el método `buscarPorCodigo(Request $request)` que recibirá el parámetro `code`.
- Este método buscará el producto donde el `barcode` o el `sku` coincida con el código escaneado (gracias al trait `BelongsToGym`, el scope del gimnasio activo se aplica automáticamente).
- Retornará un JSON con la estructura solicitada: `{ encontrado: bool, producto: object|null }`.
- Se validará el nuevo campo `barcode` en el método `validarDatos` con regla de unicidad por `gym_id` (ignorando nulos).

#### [MODIFY] `routes/admin.php`
- Se añadirá la ruta `Route::get('inventario/buscar-por-codigo', [ProductController::class, 'buscarPorCodigo'])->name('inventario.buscar-por-codigo');` dentro del grupo que cuenta con el middleware `permiso:inventario.gestionar`. IMPORTANTE: Esta ruta se colocará *antes* de la ruta `inventario/{producto}` para evitar colisiones.

### Frontend

#### [MODIFY] `resources/views/admin/inventario/index.blade.php`
- Se modificará el marcado del modal de producto para incluir dos pestañas usando la clase `.pestanas__nav`:
  - **Pestaña "Escribir"**: Contendrá el formulario actual.
  - **Pestaña "Escanear"**: Contendrá el contenedor de la cámara (`<div id="lector-codigo"></div>`) y los botones de control.
- Se añadirá el script de `html5-qrcode` vía CDN.
- Se ampliará el objeto Alpine `editorGenerico` (o se creará un wrapper si es necesario) para manejar el estado de las pestañas, la inicialización/detención de la cámara de forma reactiva, y la petición AJAX (fetch) al detectar un código.
- Se añadirá el nuevo campo oculto o visible `barcode` al formulario para que pueda ser enviado al controlador.

#### [MODIFY] `resources/css/panel.css`
- Se añadirán las clases CSS necesarias para el preview de la cámara y los mensajes de estado usando los tokens existentes (ej. `var(--r-2)`, `var(--obsidiana)`).
- Se asegurará de que sea responsive, ocupando todo el ancho en móviles.

## Verification Plan

### Manual Verification
1. Abrir el panel de inventario y hacer clic en "Nuevo producto".
2. Verificar que aparezcan las dos pestañas ("Escribir" y "Escanear").
3. Ir a la pestaña "Escanear" y activar la cámara.
4. Escanear un código de barras de un producto inexistente: debe mostrar aviso amarillo y prellenar el campo de código.
5. Completar el guardado, crear el producto y luego volver a escanear ese mismo código: debe mostrar aviso verde y prellenar todos los datos.
6. Probar en un dispositivo móvil (o emulador de navegador) para confirmar que el aspecto del lector de cámara es responsive.
