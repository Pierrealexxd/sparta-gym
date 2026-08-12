/**
 * qr.js
 * --------------------------------------------------------------
 * El QR de carnet se genera en el navegador, no en PHP: una librería de
 * ~3kb es más barata que arrastrar una extensión de imágenes en el
 * servidor sólo para esto (ver docs/mitigacion-brechas.md, Parte J).
 *
 * El valor codificado es siempre `qr_token` tal cual — el mismo que ya
 * reconoce AttendanceController al marcar asistencia por texto.
 */
import QRCode from 'qrcode';

function token(nombre) {
    return getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
}

function construir(canvas) {
    const valor = canvas.dataset.qr;
    if (!valor) return;

    QRCode.toCanvas(canvas, valor, {
        width: Number(canvas.dataset.qrTamano) || 220,
        margin: 1,
        color: {
            dark: canvas.dataset.qrOscuro || token('--obsidiana') || '#0A0A0B',
            light: canvas.dataset.qrClaro || '#FFFFFF',
        },
    });
}

export function iniciarQr() {
    document.querySelectorAll('[data-qr]').forEach(construir);
}
