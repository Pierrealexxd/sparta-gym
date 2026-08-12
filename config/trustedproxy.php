<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Trusted Proxy
|--------------------------------------------------------------------------
|
| La app vive detrás de un proxy (ngrok en desarrollo, balanceador o CDN en
| producción). Sin esto, Laravel genera las URLs de `asset()`/`route()` con
| el scheme de la conexión directa: por https vía ngrok, los assets salían
| como `http://...` y el navegador los bloqueaba como mixed content (CSS sin
| cargar, diseño roto).
|
| `proxies => '*'` confía en la cabecera X-Forwarded-* del proxy inmediato
| (ngrok se conecta desde la propia máquina) y el scheme/host generado sigue
| al que ve el navegador. El día que haya un proxy conocido se puede acotar
| a sus IPs.
*/

return [

    'proxies' => '*',

    'headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO,

];
