/**
 * carnet.js
 * --------------------------------------------------------------
 * Entrada de Vite aparte para la vista de impresión de carnet
 * (admin/socios/carnet.blade.php): esa vista es blanco-sobre-negro y
 * autónoma a propósito (ver imprimir-pagos.blade.php), así que no carga
 * app.css/app.js completos — sólo lo necesario para pintar el QR.
 */
import { iniciarQr } from './qr';

iniciarQr();
