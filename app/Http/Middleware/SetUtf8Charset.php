<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asegura que toda respuesta HTML lleve Content-Type con charset=utf-8.
 * Evita que el navegador interprete emojis y acentos como caracteres
 * equivalentes (los "��" que aparecen cuando falta la cabecera).
 */
class SetUtf8Charset
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->headers->has('Content-Type')
            && str_contains($response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        }

        return $response;
    }
}
