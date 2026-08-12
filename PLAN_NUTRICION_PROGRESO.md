# Plan — Nutrición y medición de platos en "Mi progreso"

**Proyecto:** Sparta Gym (Laravel 12 · Blade · CSS propio · Alpine · GSAP · MySQL)
**Módulo objetivo:** `/mi-cuenta/progreso` (cliente) + ficha del socio en el panel del entrenador.
**Fecha:** 2026-08-11
**Estado:** Propuesta. Sin código todavía.

---

## 1. Por qué

El módulo de progreso hoy mide la **salida** (peso, grasa, IMC) pero no la **entrada** (lo que se come). El gimnasio pide registrar el peso a diario —ese hábito ya funciona—, pero deja la alimentación a la suerte del socio. La nutrición es la otra mitad del resultado y no existe ni un campo en la base de datos que la toque.

La oportunidad es además **barata y sin dependencias**: no hace falta una base de alimentos de 14 millones de entradas ni IA de fotos. Existe un método validado que mide porciones **con la mano**, sin balanza, que es ~95% tan preciso como pesar la comida (Precision Nutrition) y que encaja con la identidad del producto: el panel ya mide progreso *contando hierro* (`<x-discos>`); esto sería *medir el plato con la mano*.

## 2. Lo que ya existe y sirve de base

| Pieza | Dónde | Qué aporta |
|---|---|---|
| Registro diario de peso/grasa | `ProgressController@guardar` (`updateOrCreate` por día) | El hábito y la serie de datos |
| Metas con tipo | `MemberGoal` (`perder_peso`, `ganar_musculo`, `fuerza`, `resistencia`, `salud`, `otro`) | El objetivo del que se derivan las porciones |
| Datos antropométricos | `members.height_cm`, `gender`, `birth_date`, `medical_notes` | Base para estimar necesidades (TMB) |
| Progreso visual | `<x-discos>` | El lenguaje visual para representar metas |
| Biblioteca compartida | `Exercise` (gym_id nulo = compartida, `scopeDisponibles`) | El patrón a copiar para las recetas |
| Multi-gimnasio | `BelongsToGym` | Las tablas nuevas lo respetan (ver §6) |

## 3. Principios del proyecto que se respetan

- **Sin dependencias externas**: nada de APIs de nutrición ni IA de fotos en el MVP. Base de alimentos propia y curada.
- **Sin Tailwind, sin colores literales**: todo con `tokens.css` y los componentes existentes.
- **Copy lacónico**: frases cortas, registro espartano.
- **Los datos del socio no son de la identidad de acceso**: separado de `users`, con `BelongsToGym`.
- **Sin prescripción clínica**: la app orienta hábitos; `medical_notes` se muestra como aviso al entrenador, nunca se usa como diagnóstico.
- **Importes/valores congelados donde importa** (la receta se copia a la comida registrada: si la receta cambia, el histórico no se reescribe).

## 4. El método de la mano (base nutricional)

La referencia para todo el plan. Cuatro porciones, una por mano, sin balanza:

| Porción | Medida | Equivale a | Macros aproximados |
|---|---|---|---|
| **Palma** | proteína | ~100 g carne/tofu, 1 yogurt griego, 2 huevos | ~24 g proteína · 145 kcal |
| **Puño** | verduras | ~1 taza (brócoli, espinaca, zanahoria) | ~25 kcal |
| **Cuenco (mano en cuenco)** | carbohidratos | ~½-⅔ taza arroz/quinoa, 1 fruta, 1 tubérculo | ~25 g carbos · 120 kcal |
| **Pulgar** | grasas | ~1 cda aceite, mantequilla de maní, queso, frutos secos | ~9 g grasa · 100 kcal |

**Referencia diaria (partida):**
- **Hombre:** 2 palmas, 2 puños, 2 cuencos, 2 pulgares por comida (~4 comidas).
- **Mujer:** 1 palma, 1 puño, 1 cuenco, 1 pulgar por comida.
- **Ajuste:** perder grasa = quitar 1-2 cuencos + 1-2 pulgares (≈ -250 kcal/día). Ganar músculo = añadir 1-2 cuencos + 1-2 pulgares (≈ +250 kcal/día).
- **Regla de oro:** anclar cada comida a la palma de proteína (objetivo ~1.2-1.6 g de proteína por kg en pérdida de peso). La proteína primero, siempre.
- **Constancia > precisión:** la porción se mide siempre de la misma forma (p. ej. siempre cocida, siempre con el mismo puño). Esto es lo que hace fiable la serie.

## 5. Fases (cada una entregable y verificable sola)

### Fase 0 — Guía "Tu balanza la llevas puesta"

**Qué:** una tarjeta en "Mi progreso" que explica las cuatro porciones de mano con la estética del proyecto (iconos de mano + discos). Botón que abre la guía completa.
**UI:** tarjeta `.tarjeta` nueva entre las metas y los gráficos, o dentro del panel derecho (`.g-2-1`).
**Datos:** ninguno (contenido estático localizado).
**Verificación:** carga en móvil sin overflow (reutilizar patrones de la auditoría responsive).

### Fase 1 — Metas nutricionales derivadas

**Qué:** a partir de la meta activa del socio (`perder_peso` / `ganar_musculo`), mostrar su referencia diaria de porciones: "2 palmas · 2 puños · 1 cuenco · 1 pulgar por comida". Se calcula en el controlador con datos que ya existen (género + tipo de meta), sin tablas nuevas.
**Lógica:** `Member::needs()` → `['palmas' => int, 'punos' => int, 'cuenco' => int, 'pulgar' => int]` según `gender` y meta; guardado **no** (se calcula al leer, igual que el IMC, para no desincronizarlo del dato que lo produce).
**UI:** bloque en "Mis metas", junto a cada meta con su `<x-discos>`.

### Fase 2 — Diario de comidas por porciones (el MVP de registro)

**Qué:** el socio registra cada comida como conteo de porciones de mano — no calorías, no gramos. Ej.: "almuerzo: 2 palmas, 1 cuenco, 2 puños, 1 pulgar".
**Datos nuevos:**
- `meal_logs` (member_id, gym_id, meal_type [desayuno/almuerzo/cena/merienda], logged_on, notes, timestamps)
- `meal_log_items` (meal_log_id, portion_type [palma/puno/cuenco/pulgar], count, food_name nullable)
**Regla:** un registro por comida por día (updateOrCreate por `[member_id, meal_type, logged_on]`), igual que el peso.
**UI:** sección "Hoy" con 4 campos numéricos (palmas, puños, cuencos, pulgares) por comida + vista diaria que suma contra la referencia de la Fase 1 (misma lógica de `<x-discos>`: "te faltan 1 palma y 2 cuencos").
**Gamificación:** racha de días con registro completo — la constancia ya se premia en el KPI "Días registrando".
**Verificación:** CRUD en el panel del entrenador (ver la ficha) + vista cliente; tests de la regla updateOrCreate.

### Fase 3 — Recetas locales con macros por porción

**Qué:** biblioteca de recetas **peruanas** con macros expresados en porciones de mano (no kcal por gramo). Ej.: "Lomo saltado: 2 palmas de proteína, 2 cuencos de arroz, 1 puño de verduras".
**Datos nuevos:**
- `recipes` (gym_id nullable = compartida, name, slug, description, ingredients, steps, prep_minutes, servings, tags)
- `recipe_portions` (recipe_id, portion_type, count, food_name) — el desglose por porción de mano
**Patrón:** copiar el de `Exercise` (`scopeDisponibles`, gym_id nulo = biblioteca compartida). **No** usa `BelongsToGym`; se explica en el docblock igual que en `Exercise`.
**Gestión:** CRUD en el panel del admin/entrenador; el entrenador asigna recetas a la ficha del socio (patrón de asignaciones existente).
**Valor local:** ventaja competitiva frente a apps *US-centric* que no reconocen platos peruanos (arroz, menestras, pollo a la brasa, camote, quinua, papa).
**Verificación:** seed con 10-15 recetas peruanas, tests de `scopeDisponibles`.

### Fase 4 — Medir sin balanza (extras)

- **Equivalencias visuales:** mazo de cartas ≈ 100 g de carne, taza ≈ cuenco, etc. — tarjeta de ayuda.
- **"Mis platos habituales":** guardar una comida registrada como plato para registrarla con un tap (el "saved meals" de MyFitnessPal).
- **Lector de etiquetas (fuera de MVP):** solo si se quiere; exige base de productos y, para ser fiable, trabajo de curaduría. Posponer.
- **Foto del plato (fuera de MVP):** requiere IA externa → choca con el principio de cero dependencias. Posponer o descartar.

## 6. Esquema de datos (resumen)

| Tabla | Notas |
|---|---|
| `recipes` | gym_id nullable (compartida), como `Exercise` |
| `recipe_portions` | desglose por porción de mano |
| `meal_logs` | BelongsToGym + unique(member, meal_type, logged_on) |
| `meal_log_items` | porciones registradas (se copia la receta si vino de una) |
| metas nutricionales | **no se guardan**: se derivan de `member_goals` + `gender` (como el IMC) |

Todas las tablas con datos de un gimnasio usan `BelongsToGym`. Solo `recipes` comparte el patrón de biblioteca compartida de `Exercise`.

## 7. Fuera de alcance (para no prometer de más)

- IA de fotos y escaneo de etiquetas (Fase 4 opcional, pospuesta).
- Planes de comidas con lista de compras (MyFitnessPal Premium+) — no tiene sentido hasta que exista el diario.
- Prescripción dietética: el sistema educa en porciones y hábitos; las indicaciones clínicas quedan en el entrenador/nutricionista.
- Sincronización con apps externas (MyFitnessPal, Apple Health).

## 8. Investigación (fuentes)

- Precision Nutrition — *Hand Portion FAQ*: el método de la mano, ~95% de precisión sin balanza, macros por porción.
- Precision Nutrition — *Hand Portion Math*: equivalencias por género y ajuste de ±250 kcal/día.
- Precision Nutrition — *Calorie Control Guide*: palma/puño/cuenco/pulgar por comida según género.
- IDEA Fit / Stephanie Kay / Muscle & Fitness: consolidación del método y objetivos de proteína (0.54-0.7 g/lb).
- TechCrunch (2025) — Ladder Nutrition: la demanda real de nutrición dentro de la app de entrenamiento; rachas/badges para el hábito; el problema de los platos internacionales en las IA de comida.
- Fortune / Garage Gym Reviews (2026): el estado del arte en apps de nutrición (diario por fotos, recetas, constancia sobre precisión).

---

## Decisión pendiente

- [ ] ¿Arrancamos la **Fase 0** (guía de porciones, solo frontend, sin migraciones)?
- [ ] ¿O planificamos primero el **esquema de datos** de las Fases 2-3 (migraciones + modelos + seed de recetas peruanas)?
- [ ] ¿Quién gestiona las recetas: admin, entrenador o ambos?
