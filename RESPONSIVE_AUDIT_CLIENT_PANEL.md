# Auditoría Responsive — Panel del Cliente

## 1. Resumen ejecutivo

**Módulos auditados:** 2 (Dashboard del cliente, Progreso del cliente)

**Componentes auditados:** 18 (KPIs, tarjetas, tablas, formularios, modales, sidebar, navegación, headers, grids, paginación, estado, discos, etc.)

**Problemas encontrados:**
- Críticos: 3
- Altos: 3
- Medios: 4
- Bajos: 3

**Principales patrones de problemas:**
- Sidebar fijo que ocupa 30% del ancho en móvil dejando contenido estrecho
- Tablas sin estrategia responsive completa (requiere clase `.tabla--tarjetas`)
- Modal ancho fijo de 48rem que puede apriete en pantallas muy estrechas
- Grid layouts con mínimos rígidos que aprietan en resoluciones pequeñas
- Botones con padding horizontal fijo que pueden desbordar en 320px

## 2. Mapa del panel auditado

| Módulo/Página | Descripción |
| ------------- | ----------- |
| **Dashboard del cliente** (`dashboard.blade.php`) | KPIs, membresía, objetivos, rutina, ventas recientes, asistencia, reseña |
| **Progreso del cliente** (`progreso.blade.php`) | KPIs peso/IMC/grasa, registro diario, metas con porciones, charts, historial medido, modal de guía |

**Componentes principales:**
- `.kpis` — grid de KPIs responsivo
- `.tarjeta` / `.tarjeta--interactiva` — cards con hover/cursor effect
- `.tabla` — tabla con scroll horizontal
- `.tabla--tarjetas` — tabla que se transforma en tarjetas en móvil
- `.formulario-panel` — formulario de 2 columnas
- `.modal__caja` / `.modal__caja--ancho` — modales ligeros
- `.panel__lateral` — barra lateral navegación
- `.membrete` — header de módulo
- `.g-2-1`, `.g-1-1` — layouts de grid de 2 y 1 columna
- `.paginacion` — paginación de elementos
- `.discos` — pilas de discos para progreso
- `.kpi` — cards KPI pequeñas
- `.estado` — badges de estado
- `.riel` — elemento firma lateral (oculto < 900px)

## 3. Problemas encontrados

| Severidad | Módulo | Componente | Archivo | Problema | Breakpoint afectado | Mitigación |
| --------- | ------ | ---------- | ------- | -------- | ------------------- | ---------- |
| CRÍTICO | Dashboard | Sidebar/lateral | panel.css:185-196 | El sidebar fijo ocupa 30% del ancho viewport (`inset: 0 30% 0 0`) dejando solo 70% para el contenido. En 320px, el contenido tiene ~224px de ancho, insuficiente para tablas y formularios. | 320-430px | Reducir a 20% o hacer que en móviles el sidebar se apile completamente sobre el contenido en lugar de ficar fijo a la derecha con ancho procentual |
| CRÍTICO | Dashboard y Progreso | Tablas sin estrategia full responsive | panel.css:446-467, 481-484 | Las tablas `.tabla` solo ocultan columnas con clase `.tabla__oculta-movil`. Si el desarrollador olvida agregar esa clase, las tablas provocan scroll horizontal incontrolable. No hay transformación a modo tarjetas a menos que se use `.tabla--tarjetas`. | 320-640px | Asegurar que todas las tablas del panel tengan la clase `.tabla--tarjetas` o `.tabla__oculta-movil` en columnas críticas; o usar detección feature-query para aplicar transformación tarjetas automáticamente | 
| CRÍTICO | Progreso | Modal `.modal__caja--ancho` | panel.css:834 | `width: min(48rem, 100%)` = hasta 768px. En 320px el `min()` correcto da 320px, pero el `max-height: 88dvh` combinado con teclado virtual iOS puede recortar contenido y crear scroll inesperado. | 320-430px | Añadir `padding: var(--e-6)` consistente y asegurar que el contenido interno use `min-width: 0` para evitar overflow; considerar `max-height: calc(88dvh - var(--e-12))` para dejar espacio al teclado |
| ALTO | Dashboard | KPIs grid | panel.css:414 | `grid-template-columns: repeat(auto-fit, minmax(210px, 1fr))` — en 320px el minmax mínimo de 210px deja apenas 110px de margen para texto, lo que puede causar truncado o wrap inesperado en los valores KPI. | 320-375px | Reducir minmax a `minmax(180px, 1fr)` o agregar `text-wrap: balance` en los valores KPI |
| ALTO | Progreso | Formulario `.formulario-panel__fila` | panel.css:666 | `grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr))` — el `min(220px, 100%)` fijo de 220px puede desbordar contenedores de < 220px antes de que `auto-fit` corrija, especialmente en 320px con padding restantes. | 320-414px | Cambiar a `minmax(clamp(180px, 5vw, 100%), 1fr)` o remover el `min(220px, ...)` y confiar en `auto-fit` solo |
| ALTO | Dashboard y Progreso | Botones con padding horizontal fijo | components.css:75, 154-156 | `.btn` tiene `padding: .95rem 1.9rem` fijo. En 320px, el padding horizontal + contenido pueden exceder el ancho disponible causando overflow en contenedores con `width: 100%`. La regla `.btn--grande` a 380px solo cubre el caso grande. | 320-380px | Usar `padding: var(--e-5) clamp(.8rem, 2vw, 1rem)` o similar fluida, y asegurar `.btn--desnudo` que ya tiene `padding-inline: 0` sea la regla padrão en móviles |
| MEDIO | Dashboard | Layout `.g-2-1` / `.g-1-1` | panel.css:870-872 | `grid-template-columns: 2fr 1fr` y `1fr 1fr` — el breakpoint a 900px es amplio. En 768px estos layouts ya están apilados, pero en 390-768px pueden quedar columnas desproporcionadas si el contenido es amplio. | 390-900px | Añadir `min-width: 0` en las celdas filhas para permitir shrinkage, y considerar breakpoints adicionales a 560px y 430px |
| MEDIO | Dashboard | Membrete `.membrete` | panel.css:364-376 | `overflow: hidden` + padding `clamp(1.5rem, 4vw, 2.5rem)` — en 320px el padding vertical es ~1.28rem (4vw), el height depende del contenido. Si el título es largo + acciones, el `overflow:hidden` puede recortar sin aviso visual. | 320-430px | Remover `overflow: hidden` o agregar `text-overflow: ellipsis` + `white-space: nowrap` en los elementos críticos del membrete |
| MEDIO | Progreso | Modal `.modal__caja` | panel.css:830-834 | `max-height: 88dvh` sin considerar la barra de teclado iOS. En iPhone SE (320px) con teclado abierto, el área útil puede reducirse a ~70vh, causando que el contenido se desborde o requiera scroll interno que el usuario no descubre. | 320-430px | Cambiar a `max-height: calc(88dvh - env(safe-area-inset-bottom))` o usar una consulta media para reducir a `75dvh` en `< 480px` |
| MEDIO | Dashboard | `.panel__usuario` en sidebar | panel.css:135-168 | En sidebar comprimido (961px+) el nombre y el formulario de logout se ocultan (`display: none`), pero en móvil (960px y abajo) el sidebar es fixed y el usuario ve avatar + nombre + form completo. En 320px, el avatar de 38px puede sentirse grande desproporcionado al lado de elementos apilados. | 320-430px | Reducir avatar a 32px en móvil o agregar `min-width: 0` en el contenedor usuario |
| BAJO | Dashboard | Paginación | panel.css:875-894 | En 480px el gapreduce a `var(--e-1)` y min-width de enlaces a 30px — suficiente. Sin embargo, en 320px con muchos elementos de paginación, el `flex-wrap: wrap` no está presente y podría haber apriete. | 320-480px | Añadir `flex-wrap: wrap` a `.paginacion__nav` como seguro para < 480px |
| BAJO | Dashboard | Discos `.discos` | components.css:280-294 | Altura fija de 46px. En combinación con fuentes de tamaño variable y el componente `x-discos` de Livewire, en 320px puede que el height no deje espacio suficiente en línea con otros elementos KPI. | 320-375px | Usar `height: clamp(3rem, 8vw, 4rem)` o `min-height` para ser más fluido |
| BAJO | Dashboard/Progreso | Textos con `white-space: nowrap` + `text-overflow: ellipsis` | panel.css:332-334, 396-404 | Uso deliberado para migas y etiquetas de sede. En móvil el texto cortado puede generar confusión si el usuario no entiende por qué se corta. No es un bug técnico pero sí un problema de usabilidad. | 320-430px | Añadir tooltips o títulos completos al pasar cursor/tecla, o usar `clamp()` para fuentes más pequeñas en móvil |

## 4. Análisis detallado

### Problema CRÍTICO 1: Sidebar fijo con 30% ancho en móvil (panel.css:185-196)

**Qué ocurre:** En resoluciones ≤ 960px, la barra lateral `.panel__lateral` cambia a `position: fixed` con `inset: 0 30% 0 0`, lo que significa que toma el 30% del ancho viewport desde el lado derecho. El contenido principal queda con `1fr` (70% restante). En un iPhone SE de 320px, eso son 96px para el sidebar y 224px para el contenido. En 375px son 112.5px vs 262.5px. En 414px son 124.2px vs 289.8px.

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
Añadir un breakpoint más granular, por ejemplo:

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

O, mejor aún, aprovechar la lógica existente de `is-abierta` para que en ≤ 380px el sidebar tome todo el ancho automáticamente.

### Problema CRÍTICO 2: Tablas sin estrategia responsive completa (panel.css:446-467, 481-484)

**Qué ocurre:** Las tablas con clase `.tabla` tienen `overflow-x: auto` en `.tabla-envoltorio` y el propio `white-space: nowrap` en celdas. A 640px o menos, la regla `.tabla__oculta-movil` oculta columnas específicas, pero:
1. Si el desarrollador no agrega la clase `tabla__oculta-movil` a columnas críticas, ninguna columna se oculta.
2. La transformación a modo tarjetas `.tabla--tarjetas` solo aplica si se usa esa clase explícita.

**Qué código lo provoca:**
```css
.tabla { width: 100%; border-collapse: collapse; font-size: var(--t-sm); }
.tabla th, .tabla td { padding: .9rem var(--e-4); text-align: left; white-space: nowrap; }
```

Y la regla oculta:
```css
@media (max-width: 640px) {
    .tabla th.tabla__oculta-movil, .tabla td.tabla__oculta-movil { display: none; }
}
```

**Por qué puede romperse en móvil:**
- En 320px-414px, si una tabla tiene 5-6 columnas y ninguna tiene la clase `tabla__oculta-movil`, el scroll horizontal es inevitable y el usuario debe desplazar horizontalmente para ver todas las datos. En pantallas táctiles esto es frustrante.
- Las tablas de asistencias y ventas en dashboard y progreso no usan `.tabla--tarjetas`, por lo que en móviles se comportan como tablas clásicas con scroll.

**Qué comportamiento debería tener:**
- Todas las tablas deberían tener automáticamente la transformación a tarjetas en ≤ 640px, o al menos las columnas críticas deberían tener la clase `tabla__oculta-movil`.
- La estrategia "tarjetas" es mucho más amigable en móvil: cada fila se convierte en una card con etiqueta:valor.

**Solución recomendada:**
1. Asegurar que todas las tablas del panel incluyan la clase `.tabla--tarjetas` o al menos marquen columnas críticas con `.tabla__oculta-movil`.
2. Considerar añadir una regla `@media (max-width: 640px)` que transforme `.tabla` a modo tarjetas automáticamente si no tiene `.tabla--tarjetas`, usando feature queries:

```css
@media (max-width: 640px) {
    .tabla:not(.tabla--tarjetas) thead { display: none; }
    .tabla:not(.tabla--tarjetas), .tabla:not(.tabla--tarjetas) tbody { display: block; width: 100%; }
    .tabla:not(.tabla--tarjetas) tbody tr { display: flex; flex-direction: column; gap: var(--e-2); margin: var(--e-3); padding: var(--e-4); border: 1px solid var(--acero); border-radius: var(--r-lg); background: var(--grafito); box-shadow: var(--s-sm); }
    .tabla:not(.tabla--tarjetas) td { display: flex; align-items: baseline; justify-content: space-between; padding: 0; white-space: normal; border: 0; background: none; color: var(--ceniza); }
    .tabla:not(.tabla--tarjetas) td::before { content: attr(data-etiqueta) ':'; ... }
}
```

### Problema CRÍTICO 3: Modal `.modal__caja--ancho` en progreso (panel.css:834)

**Qué ocurre:** El modal variante ancha tiene `width: min(48rem, 100%)`. 48rem = 768px aproximadamente. En un iPhone SE de 320px, el min() selecciona 320px (el 100%), lo cual está bien. Pero el problema es el `max-height: 88dvh` combinado con el teclado virtual en iOS.

**Qué código lo provoca:**
```css
.modal__caja { width: min(34rem, 100%); max-height: 88dvh; overflow-y: auto; padding: var(--e-6); }
.modal__caja--ancho { width: min(48rem, 100%); }
```

**Por qué puede romperse en móvil:**
- En iPhone SE con teclado abierto, el área útil del viewport se reduce drásticamente (a veces a 70vh o menos). Un modal con `max-height: 88dvh` puede hacer que el contenido se desborde o requiera scroll interno que el usuario no descubre tocando.
- El padding `var(--e-6)` = 2rem en la parte superior e inferior del modal ya reduce el espacio útil disponible.

**Qué comportamiento debería tener:**
- El modal debería tener en cuenta el espacio del teclado en dispositivos pequeños.
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

## 5. Componentes correctamente adaptados (OK)

Los siguientes componentes ya tienen una implementación responsive adecuada y **no deberían modificarse**:

| Componente | Breakpoints | Justificación |
| ---------- | ----------- | ------------- |
| `.kpis` grid | 960px (a 1 columna) | `grid-template-columns: repeat(auto-fit, minmax(210px, 1fr))` apila correctamente a partir de 960px hacia abajo. En 320px muestra 1 columna KPI. **OK** |
| `.g-2-1` / `.g-1-1` | 900px (a 1 columna) | `@media (max-width: 900px) { .g-2-1, .g-1-1 { grid-template-columns: 1fr; } }` — correcto y suficiente. **OK** |
| `.tabla--tarjetas` → móvil | 640px (transformación a cards) | La transformación completa a tarjetas con `data-etiqueta` es robusta y bien implementada. **OK** |
| `.riel` oculto | 900px | `.riel { display: none; }` a 900px es decisión de diseño intencional — el elemento de marca fijo no tiene sentido en móvil estrecho. **OK** |
| `.paginación` a 480px | 480px | Las reglas a 480px reducen gap y min-width de enlaces adecuadamente. **OK** |
| `.btn--desnudo` sin border-radius | Sin breakpoint necesario | Ya tiene `padding-inline: 0` y `border-radius: 0` por diseño. Funciona en todos los breakpoints. **OK** |
| `.discos` height 46px | Sin problema mayor | Altura fija es intencional para consistencia visual con KPIs. **OK** |
| Navegación sidebar → móvil | 960px | Transformación de grid lateral a sidebar fixed + overlay menu `.is-abierta` funciona correctamente. **OK** |
| Formulario `.formulario-panel__fila` minmax | General | `minmax(min(220px, 100%), 1fr)` con la corrección de panel.css:662-666 evita desbordes en contenedores estrechos. **OK** |

## 6. Patrones globales

1. **Breakpoints inconsistentes:** El proyecto usa múltiples breakpoints (960px, 900px, 740px, 640px, 520px, 480px, 380px) sin una escala armónica. Esto puede causar que componentes similares tengan puntos de quiebre diferentes. Ejemplo: `.ficha` y `.perfil` cambian a 900px, `.g-2-1`/` .g-1-1` a 900px, pero `.riel` a 900px también, y las tablas a 640px. No hay un breakpoint "estándar" único.

2. **Uso excesivo de `min-width` rígido:** Varios componentes tienen mínimos duros que solo se corrigen en breakpoints específicos:
   - `.ficha` tiene `300px` mínimo (corregido a 900px)
   - `.perfil` tiene `320px` mínimo (corregido a 900px)
   - `.chat` tiene `340px` mínimo (corregido a 900px)
   - `.btn` tiene padding `1.9rem` fijo
   - Los inputs `.campo__control` tienen ancho 100% pero padding horizontal fijo

3. **Tablas estrategia dual:** El proyecto tiene dos estrategias para tablas en móvil:
   - `.tabla__oculta-movil`: oculta columnas específicas (requiere agregar clase HTML)
   - `.tabla--tarjetas`: transforma toda la tabla a cards (más amigable pero requiere clase HTML)
   - Brecha: si un desarrollador no conoce ambas estrategias, puede terminar con tablas que tienen scroll horizontal incontrolable en móvil.

4. **Modais con alturas basadas en `dvh`:** El uso de `88dvh` y `100%` para modals no considera el espacio del teclado en iOS. Esto afecta particularmente al modal de guía nutricional en `progreso.blade.php` y podría afectar cualquier modal futuro.

5. **Sidebar: de grid fijo a overlay móvil:** La transición de `.panel` con `grid-template-columns: 260px 1fr` a `1fr` a ≤ 960px, más la transformación del sidebar de `sticky` a `fixed` con `transform: translateX(-100%)` es una estrategia coherente, pero el ancho del 30% fijo es el punto más crítico del panel en móvil.

## 7. Orden recomendado de mitigación

1. **Críticos (ordena inmediato):**
   - Reducir ancho sidebar de 30% a 20% a ≤ 960px, y agregar regla a ≤ 380px para sidebar full-width o hidden
   - Asegurar que todas las tablas tengan `.tabla--tarjetas` o clases `tabla__oculta-movil`
   - Ajustar modal `.modal__caja--ancho` max-height en ≤ 480px para considerar teclado iOS

2. **Altos (próximos sprints):**
   - Revisar y reducir min-maxs rígidos en KPIs (.kpis minmax 210px) y formularios (.formulario-panel__fila min(220px, 100%))
   - Revisar padding de botones `.btn` para que sea más fluido en 320px
   - Añadir `flex-wrap: wrap` a `.paginacion__nav` como seguro para < 480px

3. **Medios (qa de seguimiento):**
   - Ajustar `.membrete` overflow/trasformación en 320px
   - Revisar `max-height` modal con `env(safe-area-inset-bottom)`
   - Considerar breakpoints adicionales a 560px y 430px en componentes críticos

4. **Bajos (mejoras menores):**
   - Ajustar height `.discos` a `clamp()` para fluidez
   - Añadir tooltips/alt-textos para textos cortados con `ellipsis`
   - Verificar avatares en móvil (38px vs 32px)

## 8. Recomendaciones para el agente que realizará las correcciones

**Instrucciones concretas:**

1. **Sidebar ancho (panel.css:185-196):**
   - Cambiar `inset: 0 30% 0 0` por `inset: 0 20% 0 0` en el breakpoint `@media (max-width: 960px)`.
   - Añadir nuevo breakpoint `@media (max-width: 380px)` que cambie `.panel__lateral` a `inset: 0 100% 0 0` o use la clase `is-abierta` existente para que el sidebar ocupe todo el ancho en móviles muy estrechos.
   - Verificar que el contenido `.panel__contenido` no quede < 300px en 320px después del cambio.

2. **Tablas responsive (panel.css:446-545):**
   - Revisar cada tabla en `dashboard.blade.php` y `progreso.blade.php` para asegurar que tengan la clase `.tabla--tarjetas` o, como mínimo, que las columnas críticas tengan `tabla__oculta-movil`.
   - Considerar añadir una regla `@media (max-width: 640px)` que transforme `.tabla:not(.tabla--tarjetas)` automáticamente al modo cards (ver análisis detallado arriba), para no depender solo de que el desarrollador recuerde agregar clases.

3. **Modal max-height (panel.css:830-834):**
   - Añadir regla `@media (max-width: 480px) { .modal__caja { max-height: calc(88dvh - env(safe-area-inset-bottom)); } }` o `@media (max-width: 480px) { .modal__caja { max-height: 75dvh; } }`.
   - Verificar el modal en `progreso.blade.php` línea 176-217 (modal de guía nutricional) y asegurar que su contenido no se desborde con el teclado abierto.

4. **KPIs min-width (panel.css:414):**
   - Considerar cambiar `minmax(210px, 1fr)` a `minmax(180px, 1fr)` para dar un poco más de respiro en 320px, o añadir `text-wrap: balance` en los valores KPI dentro de `dashboard.blade.php`.

5. **Botones padding (components.css:69-92, 154-156):**
   - Cambiar `.btn` padding de `padding: .95rem 1.9rem` a `padding: .95rem clamp(1rem, 3vw, 1.5rem)` o similar fluida.
   - Verificar que la regla a 380px siga siendo necesaria o si la regla fluida la cubre.

6. **Membrete overflow (panel.css:364-376):**
   - Considerar remover `overflow: hidden` del `.membrete` o añadir `text-overflow: ellipsis` + `white-space: nowrap` solo en `.membrete__titulo` si es necesario, para evitar recorte sorpresivo en 320px.

7. **No tocar lo siguiente (YA ESTÁ OK):**
   - `.kpis` grid — funciona correctamente
   - `.g-2-1` / `.g-1-1` a 900px — correcto
   - `.tabla--tarjetas` transformación — ya implementada
   - `.riel` oculto a 900px — decisión de diseño
   - Navegación sidebar → móvil — funciona con `.is-abierta`
   - Paginación a 480px — suficiente
   - `.discos` height 46px — intencional
   - `.btn--desnudo` — diseño consistente

---
*Fin de la auditoría responsive del panel del cliente. No se modificaron archivos del proyecto.*