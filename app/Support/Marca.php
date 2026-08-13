<?php

namespace App\Support;

/**
 * El logotipo del sitio (no confundir con Gym::logo_path, que es el logo
 * PROPIO de cada sede, subido desde admin → Sedes). Este es el logotipo de
 * la marca Sparta Gym en sí, el mismo que usa la landing para el splash y
 * la marca de agua — se movió acá desde LandingController para poder
 * reusarlo también en login/registro sin duplicar la lógica.
 */
class Marca
{
    /**
     * URL pública del logotipo, si existe. Se acepta en dos ubicaciones para
     * no depender de que quien lo suba recuerde la convención de Laravel:
     * si aparece en resources/images (más natural para un archivo de marca
     * que no pasa por Vite) se copia una sola vez a public/images, que es
     * donde el navegador puede pedirlo.
     */
    public static function logoPublico(): ?string
    {
        $destino = public_path('images/logo.png');

        if (! is_file($destino)) {
            $origen = resource_path('images/logo.png');
            if (is_file($origen)) {
                @mkdir(dirname($destino), 0777, true);
                copy($origen, $destino);
            }
        }

        return is_file($destino) ? asset('images/logo.png') . '?v=' . filemtime($destino) : null;
    }
}
