# Auditoría Responsive — Panel del Cliente (Administrador)

## 1. Resumen ejecutivo

**Módulos auditados:** 1 (Dashboard del administrador)

**Componentes auditados:** 15 (KPIs, tablas, gráficos, sidebar, navegación, headers, grids, formularios, modales, modales de wizard)

**Problemas encontrados:**
- Críticos: 4
- Altos: 2
- Medios: 2
- Bajos: 3

**Principales patrones de problemas:**
- Sidebar fijo que ocupa 30% del ancho en móvil dejando contenido ancho
- KPIs que pueden mostrar 2 en fila en móvil ancho (210px min) vs 1 en móvil estrecho
- Tablas sin estrategia responsive completa (algunas sin `.tabla--tarjetas`)
- Modales con `max-height: 88dvh` sin considerar teclado iOS
- Formularios con `min(220px, 100%)` ancho mínimo rígido

## 2. Mapa del panel auditado

| Módulo/Página | Descripción |
| ------------- | ----------- |
| **Dashboard del administrador** (`dashboard.blade.php`) | 6 KPIs, 5 gráficos, tablas de "vencen esta semana" y "últimas ventas", filtros por sede |

**Componentes principales:**
- `.kpis` — grid de 6 KPIs con `auto-fit minmax(210px, 1fr)`
- `.tarjeta` / `.tarjeta--interactiva` — cards con hover/cursor effect
- `.tabla` — tabla con scroll horizontal
- `.tabla--tarjetas` — tabla que se transforma en tarjetas en móvil (solo en algunos módulos)
- `.g-2-1` y `.g-1-1` — layouts de grid con gráficos
- `.tabla-envoltorio` — wrapper con overflow-x: auto
- `.modal__caja` / `.modal__caja--ancho` — modales ligeros
- `.panel__lateral` — barra lateral navegación admin
- `.kpi` — cards KPI pequeñas con icono, valor, etiqueta
- `.filtro-sedes` — selector de sede (solo multi-sede)
- `.wizard__pasos` — pasos de matrícula

---

## 3. Problemas encontrados

| Severidad | Módulo | Componente | Archivo | Problema | Breakpoint afectado | Mitigación |
| --------- | ------ | ---------- | ------- | -------- | ------------------- | ---------- |
| CRÍTICO | Dashboard | Sidebar/lateral | panel.css:185-196 | El sidebar fijo ocupa 30% del ancho viewport (`inset: 0 30% 0 0`) dejando solo 70% para el contenido. En 320px, el contenido tiene ~224px de ancho, insuficiente para tablas y formularios. | 320-430px | Reducir a 20% o hacer que en móviles el sidebar se apile completamente sobre el contenido en lugar de ficar fijo a la derecha con ancho procentual |
| CRÍTICO | Dashboard | KPIs grid | panel.css:414 | `grid-template-columns: repeat(auto-fit, minmax(210px, 1fr))` con 6 KPIs — en 320px el minimo 210px deja apenas 110px de margen para texto, pero en 430px el panel puede mostrar 2 KPIs por fila (210 × 2 = 420px ≈ 430px), rompiendo la consistencia visual de "uno en uno" que el usuario solicitó. | 320-430px | Reducir minmax a `minmax(180px, 1fr)` para forzar 1 por fila, o agregar breakpoints a 360px y 300px donde solo 1 KPI por fila se muestra |
| CRÍTICO | Dashboard | Tablas sin estrategia full responsive | panel.css:446-467, 481-484 + dashboard tables | Las tablas `.tabla` en el dashboard (líneas 148-163, 169-183) no usan `.tabla--tarjetas`. En móvil (< 640px), provocan scroll horizontal incontrolable. Las tablas sí tienen `tabla__oculta-movil` en columnas, pero no la transformación a tarjetas. | 320-640px | Asegurar que todas las tablas del dashboard tengan `.tabla--tarjetas` o añadan clases `tabla__oculta-movil` a columnas críticas; considerar feature-query auto-transformation a tarjetas en ≤ 640px |
| CRÍTICO | Dashboard | Modal `.modal__caja--ancho` | panel.css:834 | `width: min(48rem, 100%)` = hasta 768px. `max-height: 88dvh` combinado con teclado virtual iOS en móviles puede recortar contenido y crear scroll inesperado. El modal de "Nueva matrícula" (wizard de 3 pasos) en 320px es especialmente apremiante. | 320-430px | Añadir `@media (max-width: 480px) { .modal__caja--ancho { width: min(36rem, 100%); } .modal__caja { max-height: calc(88dvh - env(safe-area-inset-bottom)); } }` o ser más conservador con `75dvh` en ≤ 480px |
| ALTO | Dashboard | Layout `.g-2-1] / .g-1-1] con gráficos | panel.css:870-872 + dashboard g-2-1/g-1-1 | Los gráficos en `.g-2-1` (2 columnas) y `.g-1-1` (2 columnas apiladas) — el breakpoint a 900px es amplio. En 768px los gráfulos graficos de `.g-2-1` apilan a 1 columna, pero en 480-768px pueden quedar columnas desproporcionadas si el contenido es amplio los contenedores internos (canvas 260px alto fijo). | 390-900px | Añadir `min-width: 0` en las celdas filhas para permitir shrinkage, y considerar breakpoints a 560px y 430px para apilar gráficos antes de 900px |
| ALTO | Dashboard | Formulario `.formulario-panel__fila` | panel.css:666 | `grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr))` — el `min(220px, 100%)` fijo de 220px puede desbordar contenedores de < 220px antes de que `auto-fit` corrija, especialmente en 320px con padding restantes y. | 320-414px | Cambiar a `minmax(clamp(180px, 5vw, 100%), 1fr)` o remover el `min(220px, ...)` y confiar en `auto-fit` solo |
| MEDIO | Dashboard | Membrete `.membrete` | panel.css:364-376 | `overflow: hidden` + padding `clamp(1.5rem, 4vw, 2.5rem)` — en 320px el padding vertical es ~1.28rem (4vw), el height depende del contenido. Si el título es largo + acciones, el `overflow:hidden` puede recortar sin aviso visual. | 320-430px | Remover `overflow: hidden` o agregar `text-overflow: ellipsis` + `white-space: nowrap` en los elementos críticos del membrete |
| MEDIO | Dashboard | Filtro "algunas sedes" `.filtro-sedes` | panel.css:1003-1015 | `display: grid; gap: var(--e-4)` con `width: fit-content` — en 320px el toggler y las opciones pueden desbordar el ancho disponible. Las checkboxes en móvil ocupan todo el ancho pero el `display: inline-flex` en el toggler no está optimizado para touch. | 320-375px | Añadir `flex-wrap: wrap` en móviles y considerar `width: 100%` en togglers; asegurar `min-width: 0` en los contenedores de opciones |
| BAJO | Dashboard | Paginación | panel.css:875-894 | En 480px el gap reduce a `var(--e-1)` y min-width de enlaces a 30px — suficiente. Sin embargo, en 320px con muchos elementos de paginación, el `flex-wrap: wrap` no está presente y podría haber apriete. | 320-480px | Añadir `flex-wrap: wrap` a `.paginacion__nav` como seguro para < 480px |
| BAJO | Dashboard | Discos `.discos] | components.css:280-294 | Altura fija de 46px. En combinación con fuentes de tamaño variable y el componente `x-discos` de Livewire, en 320px puede que el height no deje espacio suficiente en línea con otros elementos KPI. | 320-375px | Usar `height: clamp(3rem, 8vw, 4rem)` o `min-height` para ser más fluido |
| BAJO | Dashboard | Textos con `white-space: nowrap` + `text-overflow: ellipsis` | panel.css:332-334, 396-404 | Uso deliberado para migas y etiquetas de sede. En móvil el texto cortado puede generar confusión si el usuario no entiende por qué se corta. No es un bug técnico pero sí un problema de usabilidad. | 320-430px | Añadir tooltips o títulos completos al pasar cursor/tecla, o usar `clamp()` para fuentes más pequeñas en móvil |

---

## 4. Análisis detallado

### Problema CRÍTICO 1: Sidebar fijo con 30% ancho en móvil (panel.css:185-196)

**Qué ocurre:** En resoluciones ≤ 960px, la barra lateral `.panel__lateral` cambia a `position: fixed` con `inset: 0 30% 0 0`, tomando el 30% del ancho viewport. El contenido principal queda con `1fr` (70% restante). En un iPhone SE de 320px, eso son 96px para el sidebar y 224px para el contenido. En 375px son 112.5px vs 262.5px. En 414px son 124.2px vs 289.8px.

**Qué código lo provoca:**
```css
@media (max-width: 960px) {
    .panel__lateral {
        position: fixed;
        inset: 0 30% 0 0;  /* <- problema: 30% fijo */
        ...
    }
}
```

**Por qué puede romperse en móvil:**
- En 320px, 224px puede ser insuficiente para tablas que necesitan columnas mínimas, para formularios con inputs que tienen padding horizontal, o para KPIs que necesitan espacio para el icono + valor + etiqueta.
- El contenido puede quedar extremadamente estrecho, forzando zoom-out indeseado o scroll horizontal en elementos internos.
- El efecto es acumulativo: si una tabla ya tiene `overflow-x: auto` en su contenedor, el contenido restante es aún más estrecho.

**Qué comportamiento debería tener:**
- En móviles pequeños (≤ 380px), el sidebar debería ocupar todo el ancho (100%) o, mejor aún, apilarse sobre el contenido y ofrecer un botón de "comprimir/expandir" similar al que ya existe para desktop.
- O alternativamente, reducir el ancho del sidebar a 20% y agregar una regla que en ≤ 380px cambie a sidebar full-width o hidden.

**Solución recomendada:**
```css
@media (max-width: 960px) {
    .panel__lateral {
        position: fixed;
        inset: 0 20% 0 0;  /* reducido de 30% a 20% */
    }
}

@media (max-width: 380px) {
    .panel__lateral {
        inset: 0 100% 0 0;  /* o quitar y que el contenido ocupe 100% */
        transform: translateX(0) !important;
    }
    .panel__lateral.is-abierta { transform: translateX(0) !important; }
}
```

### Problema CRÍTICO 2: KPIs grid con 6 items que may show 2 per row en móvil ancho (panel.css:414)

**Qué ocurre:** El KPI grid usa `repeat(auto-fit, minmax(210px, 1fr))`. Con 6 KPIs admin:
- At 320px: 1 column of 210px fits, leaving 110px overflow. Consistent single column.
- At 430px: the math 210 × 2 = 420px + var(--e-4) gap ≈ 421px. This CAN fit in 430px, showing 2 KPIs per row. This breaks the "uno en uno" (single column) visual consistency the user requested for mobile.

**Qué código lo provoca:**
```css
.kpis { display: grid; gap: var(--e-4); grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); }
```

**Por qué puede romperse en móvil:**
- The user specifically wants KPIs "uno en uno" (single column) in mobile. Currently, at ~430px viewport, 2 KPIs per row appear, creating inconsistent visual rhythm across breakpoints. Some mobile devices at 430px (like some small Android phones) will show 2 per row while others at 375px (iPhone SE) show 1 per row, creating unpredictable UI.

**Qué comportamiento debería tener:**
- Consistent single KPI per row in all mobile breakpoints ≤ ~400px, or explicitly controlled breakpoints where 2 per row starts.

**Solución recomendada:**
```css
.kpis { display: grid; gap: var(--e-4); grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
```
This reduces the minimum KPI width from 210px to 180px, ensuring 1 per row even at 360px (180px fits with 180px gapless math: 180 × 1 = 180 < 360; 180 × 2 = 360 = breakpoint threshold, but with `auto-fit` it may still stack; safer to force 1-per-row with `grid-template-columns: 1fr` at narrower, or keep 180px min where 2-per-row only at >400px).

Alternative: Use explicit media queries:
```css
@media (max-width: 400px) { .kpis { grid-template-columns: 1fr; } }
```
Keeping 2-per-row only above 400px.

### Problema CRÍTICO 3: Tablas sin estrategia responsive completa (dashboard tables, panel.css:446-545)

**Qué ocurre:** The dashboard tables at lines 148-163 (Vencer esta semana) and 169-183 (Últimas ventas) use `.tabla` without `.tabla--tarjetas`. Some admin tables do have `.tabla--tarjetas` (clientes index at line 66: `<table class="tabla tabla--tarjetas">`), but the dashboard tables don't.

**Qué código lo provoca:**
```html
<!-- Dashboard tables: NO .tabla--tarjetas -->
<article class="tarjeta">
    <div class="tabla-envoltorio">
        <table class="tabla">
```
vs
```html
<!-- Clientes index: HAS .tabla--tarjetas -->
<table class="tabla tabla--tarjetas">
```

**Por qué puede romperse en móvil:**
- En 320px-414px, if a table has 5-6 columns and ninguna tiene la clase `tabla__oculta-movil`, el scroll horizontal es inevitable y el usuario debe desplazar horizontalmente para ver todas los datos. En pantallas táctiles esto es frustrante.
- The dashboard tables have 3 columns (Cliente, Código, Vence) or 3 columns (Cliente, Monto, Método). These are manageable but still cause horizontal scroll on narrow mobile.

**Qué comportamiento debería tener:**
- All dashboard tables should have `.tabla--tarjetas` transformation in móvil, or at minimum mark critical columns with `.tabla__oculta-movil`.
- The "tarjetas" strategy is much more amigable en móvil: cada fila se convierte en una card con etiqueta:valor.

**Solución recomendada:**
1. Add `.tabla--tarjetas` class to dashboard tables in `dashboard.blade.php`, or
2. Add feature-query CSS to auto-transform `.tabla:not(.tabla--tarjetas)` at ≤ 640px:
```css
@media (max-width: 640px) {
    .tabla:not(.tabla--tarjetas) thead { display: none; }
    .tabla:not(.tabla--tarjetas), .tabla:not(.tabla--tarjetas) tbody { display: block; width: 100%; }
    .tabla:not(.tabla--tarjetas) tbody tr { display: flex; flex-direction: column; gap: var(--e-2); margin: var(--e-3); padding: var(--e-4); border: 1px solid var(--acero); border-radius: var(--r-lg); background: var(--grafito); box-shadow: var(--s-sm); }
    .tabla:not(.tabla--tarjetas) td { display: flex; align-items: baseline; justify-content: space-between; padding: 0; white-space: normal; border: 0; background: none; color: var(--ceniza); }
    .tabla:not(.tabla--tarjetas) td::before { content: attr(data-etiqueta) ':'; ... }
}
```

### Problema CRÍTICO 4: Modal `.modal__caja--ancho` en dashboard (panel.css:834)

**Qué ocurre:** The modal variant ancha has `width: min(48rem, 100%)`. 48rem = 768px aproximadamente. En un iPhone SE de 320px, el min() selecciona 320px (el 100%), lo cual está bien. Pero el problema es el `max-height: 88dvh` combinado con el teclado virtual en iOS.

**Qué código lo provoca:**
```css
.modal__caja { width: min(34rem, 100%); max-height: 88dvh; overflow-y: auto; padding: var(--e-6); }
.modal__caja--ancho { width: min(48rem, 100%); }
```

**Por qué puede romperse en móvil:**
- En iPhone SE with teclado abierto, el área útil del viewport se reduce drásticamente (a veces a 70vh o menos). Un modal with `max-height: 88dvh` puede hacer que el contenido se desborde o requiera scroll interno que el usuario no discovers tocando.
- The dashboard has a "matricula wizard" modal (3 steps) at lines 394-530 of admin/clientes/index.blade.php that is `modal__caja wizard`. This is especially wide and long.

**Qué comportamiento debería tener:**
- The modal should have en cuenta el espacio del teclado en dispositivos pequeños.
- O al menos, el `max-height` debería ser más conservador en ≤ 480px.

**Solución recomendada:**
```css
@media (max-width: 480px) {
    .modal__caja--ancho { width: min(36rem, 100%); }
    .modal__caja { max-height: calc(88dvh - env(safe-area-inset-bottom)); }
}
```

O ser más conservador:
```css
@media (max-width: 480px) {
    .modal__caja--ancho { width: min(30rem, 100%); }
    .modal__caja { max-height: 75dvh; }
}
```

---

## 5. Componentes correctamente adaptados (OK)

Los siguientes componentes ya tienen una implementación responsive adecuada y **no deberían modificarse**:

| Componente | Breakpoints | Justificación |
| ---------- | ----------- | ------------- |
| `.kpis` grid | 960px (a 1 columna) | `grid-template-columns: repeat(auto-fit, minmax(210px, 1fr))` apila correctamente a partir de 960px hacia abajo. En 320px muestra 1 columna KPI. **OK** (aunque el número de items por fila cambia en ~430px — ver análisis Crítico 2) |
| `.g-2-1` / `.g-1-1` | 900px (a 1 columna) | `@media (max-width: 900px) { .g-2-1, .g-1-1 { grid-template-columns: 1fr; } }` — correcto y suficiente. **OK** |
| `.tabla--tarjetas` → móvil | 640px (transformación a cards) | La transformación completa a tarjetas con `data-etiqueta` es robusta y bien implementada. Algunos admin tables already have this (clientes index). **OK] |
| `.riel` oculto | 900px | `.riel { display: none; }` a 900px es decisión de diseño intencional — el elemento de marca fijo no tiene sentido en móvil estrecho. **OK** |
| `.paginación` a 480px | 480px | Las reglas a 480px reducen gap y min-width de enlaces adecuadamente. **OK** |
| `.btn--desnudo` sin border-radius | Sin breakpoint necesario | Ya tiene `padding-inline: 0` y `border-radius: 0` por diseño. Funciona en todos los breakpoints. **OK** |
| `.discos` height 46px | Sin problema mayor | Altura fija es intencional para consistencia visual. **OK] |
| Navegación sidebar → móvil | 960px | Transformación de grid lateral a sidebar fixed + overlay menu `.is-abierta` funciona correctamente. **OK] |
| Formulario `.formulario-panel__fila` minmax | General | `minmax(min(220px, 100%), 1fr)` con la corrección de panel.css:662-666 evita desbordes en contenedores estrechos. **OK] |

---

## 6. Patrones globales

1. **Breakpoints inconsistentes:** El proyecto usa múltiples breakpoints (960px, 900px, 640px, 520px, 480px, 380px) sin una escala armónica. El admin comparte estos mismos breakpoints con el cliente panel.

2. **Uso excesivo de `min-width` rígido:** Varios componentes tienen mínimos duros que solo se corrigen en breakpoints específicos:
   - `.ficha` tiene `300px` mínimo (corregido a 900px)
   - `.perfil` tiene `320px` mínimo (corregido a 900px)
   - `.chat` tiene `340px` mínimo (corregido a 900px)
   - `.btn` tiene padding `1.9rem` fijo
   - Los inputs `.campo__control` tienen ancho 100% pero padding horizontal fijo

3. **Tablas estrategia dual:** El proyecto tiene dos estrategias para tablas en móvil:
   - `.tabla__oculta-movil`: oculta columnas específicas (requiere agregar clase HTML)
   - `.tabla--tarjetas`: transforma toda la tabla a cards (más amigable pero requiere clase HTML)
   - Brecha: si un desarrollador no conoce ambas estrategias, puede terminar con tablas que tienen scroll horizontal incontrolable en móvil. Dashboard tables missed `.tabla--tarjetas`.

4. **Modais con alturas basadas en `dvh`:** El uso de `88dvh` y `100%` para modals no considera el espacio del teclado en iOS. Esto afecta particularmente al modal de "Nueva matrícula" (wizard de 3 pasos) en `clientes/index.blade.php` y a cualquier modal amplio en admin.

5. **Sidebar: de grid fijo a overlay móvil:** La transición de `.panel` con `grid-template-columns: 260px 1fr` a `1fr` a ≤ 960px, más la transformación del sidebar de `sticky` a `fixed` con `transform: translateX(-100%)` es una estrategia coherente, pero el ancho del 30% fijo es el punto más crítico del panel en móvil.

6. **Wizard modals son especialmente anchos:** Los modales de matrícula (`wizard`) en `clientes/index.blade.php` usan `modal__caja wizard` class con contenido de 3 pasos+resumen final, haciendo que el `modal__caja--ancho` de 48rem sea particularmente problemático en móvil. El contenido se encadena verticalmente pero the width at 48rem = 768px means on 320px iPhone SE the `min()` selects 100% = 320px which is fine, but the height `88dvh` may fight with keyboard.

---

## 7. Orden recomendado de mitigación

1. **Críticos (ordena inmediato):**
   - Reducir ancho sidebar de 30% a 20% a ≤ 960px, y agregar regla a ≤ 380px para sidebar full-width o hidden
   - Asegurar que todas las tablas del dashboard tengan `.tabla--tarjetas` o clases `tabla__oculta-movil`
   - Ajustar modal `.modal__caja--ancho` max-height en ≤ 480px para considerar teclado iOS
   - Revisar KPIs grid minwidth: reducir de 210px a 180px o añadir media query `max-width: 400px { .kpis { grid-template-columns: 1fr; } }` para force single column

2. **Altos (próximos sprints):**
   - Revisar y reducir min-maxs rígidos en formularios (.formulario-panel__fila min(220px, 100%))
   - Revisar padding de botones `.btn` para que sea más fluido en 320px
   - Añadir `flex-wrap: wrap` a `.paginacion__nav` como seguro para < 480px
   - Considerar breakpoints adicionales a 560px y 430px en componentes críticos

3. **Medios (qa de seguimiento):**
   - Ajustar `.membrete` overflow/trasformación en 320px
   - Revisar `max-height` modal con `env(safe-area-inset-bottom)`
   - Considerar `width: 100%` + `flex-wrap: wrap` en filtro "algunas sedes"
   - Añadir tooltips para textos cortados con `ellipsis`

4. **Bajos (mejoras menores):**
   - Ajustar height `.discos` a `clamp()` para fluidez
   - Verificar avatares en móvil (38px vs 32px)
   - Añadir `min-width: 0` en celdas de filtro toggler para permitir shrink

---

## 8. Recomendaciones para el agente que realizará las correcciones

**Instrucciones concretas:**

1. **Sidebar ancho (panel.css:185-196):**
   - Cambiar `inset: 0 30% 0 0` por `inset: 0 20% 0 0` en el breakpoint `@media (max-width: 960px)`.
   - Añadir nuevo breakpoint `@media (max-width: 380px)` que cambie `.panel__lateral` a `inset: 0 100% 0 0` o use la clase `is-abierta` existente para que el sidebar ocupe todo el ancho en móviles muy estrechos.
   - Verificar que el contenido `.panel__contenido` no quede < 300px en 320px después del cambio.

2. **KPIs grid single column (panel.css:414):**
   - **Opción A:** Cambiar `.kpis` de `minmax(210px, 1fr)` a `minmax(180px, 1fr)` para asegurar 1 por fila en todo móvil (210px mínimo es wide; 180px permite 1 per row down to ~360px).
   - **Opción B:** Añadir media query: `@media (max-width: 400px) { .kpis { grid-template-columns: 1fr; } }` para force single KPI per row in narrow mobile, keeping 2-per-row only above 400px where the user has more screen real estate.
   - **Recomendado:** Opción B, ya que el usuario solicitó "uno en uno" pero may appreciate 2-per-row on wider mobiles (some Android phones at 430px).

3. **Tablas responsive (panel.css:446-545 + dashboard.blade.php):**
   - Add `.tabla--tarjetas` class to dashboard tables in `dashboard.blade.php` lines 148-163 and 169-183, or add the feature-query CSS auto-transformation at ≤ 640px to transform `.tabla:not(.tabla--tarjetas)` to card mode.
   - Verify all admin tables have the strategy; the clientes index already has `.tabla.tabla--tarjetas`, the dashboard tables need it added.

4. **Modal max-height (panel.css:830-834):**
   - Añadir regla `@media (max-width: 480px) { .modal__caja { max-height: calc(88dvh - env(safe-area-inset-bottom)); } }` o `@media (max-width: 480px) { .modal__caja { max-height: 75dvh; } }`.
   - Verify the dashboard "Nueva matrícula" wizard modal (clientes/index.blade.php línea 394-530) and ensure its content doesn't overflow with keyboard open.

5. **No tocar lo siguiente (YA ESTÁ OK):**
   - `.kpis` grid — funciona correctamente (ver discusión en Óptima 5)
   - `.g-2-1` / `.g-1-1` a 900px — correcto
   - `.tabla--tarjetas` transformación — ya implementado en algunos admin tables
   - `.riel` oculto a 900px — decisión de diseño
   - Naveigación sidebar → móvil — funciona con `.is-abierta`
   - Paginación a 480px — suficiente
   - `.discos` height 46px — intencional
   - `.btn--desnudo` — diseño consistente

---
*Fin de la auditoría responsive del panel del administrador. No se modificaron archivos del proyecto.*