/**
 * graficos.js
 * --------------------------------------------------------------
 * Envuelve Chart.js con la paleta de Sparta, leída de tokens.css en vez de
 * repetirla en JS: si el diseño cambia un color, los gráficos lo heredan
 * solos.
 *
 * Los datos llegan como JSON en el propio atributo data-grafico —el
 * controlador ya los agrega en la consulta— así que este módulo no hace
 * peticiones ni conoce la forma de los modelos.
 */

import {
    Chart, LineController, BarController, DoughnutController,
    LineElement, BarElement, ArcElement, PointElement,
    CategoryScale, LinearScale, Filler, Tooltip, Legend,
} from 'chart.js';

Chart.register(
    LineController, BarController, DoughnutController,
    LineElement, BarElement, ArcElement, PointElement,
    CategoryScale, LinearScale, Filler, Tooltip, Legend
);

function token(nombre) {
    return getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
}

/** Hex de token (#RRGGBB) a rgba con la opacidad pedida, para canvas. */
function conAlfa(hex, alfa) {
    const n = parseInt(hex.replace('#', '').slice(0, 6), 16);
    return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alfa})`;
}

/**
 * Degradado vertical desde el color del dato. Línea: velo fino que se apaga;
 * barra: hierro caliente que se enfría hacia abajo. Cada dataset puede pedir
 * su token (p. ej. --sangre) para que la paleta salga del diseño, no del JS.
 *
 * Chart.js pide este color ANTES de que el layout termine de calcular el
 * área del gráfico — en esa primera pasada `chart.chartArea` todavía no
 * existe, así que el "?? {}" de quien llama a esta función deja `area.top`
 * y `area.bottom` en `undefined`. createLinearGradient con bordes
 * undefined revienta con "The provided double value is non-finite" — y
 * como iniciarGraficos() recorre TODOS los <canvas> en un solo try/catch,
 * un solo gráfico fallando aquí apagaba los demás de la página entera
 * (dashboard admin, Mi progreso, cualquiera). Mientras el área no tenga
 * números reales, se devuelve el color plano; Chart.js vuelve a pedir el
 * degradado en la siguiente pasada, ya con el área calculada.
 */
function degradadoVertical(ctx, area, color, tipo) {
    if (!area || !Number.isFinite(area.top) || !Number.isFinite(area.bottom)) {
        return conAlfa(color, tipo === 'bar' ? .9 : .25);
    }

    const g = ctx.createLinearGradient(0, area.top, 0, area.bottom);
    if (tipo === 'bar') {
        g.addColorStop(0, conAlfa(color, .9));
        g.addColorStop(1, conAlfa(color, .3));
    } else {
        g.addColorStop(0, conAlfa(color, .35));
        g.addColorStop(1, conAlfa(color, 0));
    }
    return g;
}

const PALETA = () => [token('--sangre'), token('--brasa'), token('--bronce'), token('--acero-claro'), token('--humo')];

Chart.defaults.font.family = "'Sora', system-ui, sans-serif";

/* Los gráficos construidos vivos, para poder reconstruirlos cuando el tema
   del panel cambia: la paleta se lee de tokens.css en cada construcción. */
const instancias = new Map();

function construir(canvas) {
    const cfg = JSON.parse(canvas.dataset.grafico);
    const tipo = cfg.tipo ?? 'line';

    Chart.defaults.color = token('--grafico-texto');
    Chart.defaults.borderColor = token('--grafico-rejilla');

    const datasets = (cfg.datasets ?? [{ data: cfg.data, label: cfg.label }]).map((ds, i) => {
        const color = token(ds.token ?? '') || ds.color || PALETA()[i % 5];

        let relleno;
        if (tipo === 'doughnut') relleno = PALETA();
        else if (tipo === 'bar') relleno = (ctx) => degradadoVertical(ctx.chart.ctx, ctx.chart.chartArea ?? {}, color, 'bar');
        else relleno = ds.relleno === false ? false : (ctx) => degradadoVertical(ctx.chart.ctx, ctx.chart.chartArea ?? {}, color, 'line');

        return {
            ...ds,
            borderColor: color,
            backgroundColor: relleno,
            borderWidth: tipo === 'line' ? 2.5 : 0,
            borderDash: ds.guiones ?? undefined,
            borderRadius: tipo === 'bar' ? 6 : 0,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: .35,
            fill: tipo === 'line' && ds.relleno !== false,
        };
    });

    const grafico = new Chart(canvas, {
        type: tipo,
        data: { labels: cfg.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: tipo === 'doughnut', position: 'bottom', labels: { boxWidth: 10, padding: 16 } },
                tooltip: {
                    backgroundColor: token('--tooltip-fondo'),
                    borderColor: token('--tooltip-borde'),
                    borderWidth: 1,
                    padding: 12,
                    titleColor: token('--tooltip-titulo'),
                    bodyColor: token('--tooltip-texto'),
                    cornerRadius: 8,
                },
            },
            scales: tipo === 'doughnut' ? {} : {
                x: { grid: { display: false } },
                y: { grid: { color: token('--grafico-rejilla-suave') }, beginAtZero: true },
            },
        },
    });

    instancias.set(canvas, grafico);
    return grafico;
}

export function iniciarGraficos() {
    // Aislado por canvas: si uno falla (dato raro, config inesperada), que
    // no se apaguen los demás gráficos de la misma pantalla con él — el
    // try/catch de iniciar() en app.js sólo protege entre módulos, no
    // dentro de este forEach.
    document.querySelectorAll('[data-grafico]').forEach((canvas) => {
        try {
            construir(canvas);
        } catch (error) {
            console.error('[graficos.js] fallo al construir un gráfico:', error);
        }
    });
}

/** Reconstruye los gráficos con la paleta del tema recién aplicado. */
export function refrescarGraficos() {
    instancias.forEach((grafico, canvas) => {
        grafico.destroy();
        instancias.delete(canvas);
        construir(canvas);
    });
}
