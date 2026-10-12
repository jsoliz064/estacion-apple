<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesion de un usuario desactivado.
 *
 * El login ya lo rechaza (Fortify::authenticateUsing en FortifyServiceProvider),
 * pero eso solo actua al entrar: sin este middleware, quien tuviera la sesion
 * abierta o la cookie de "recordarme" seguiria trabajando hasta que caducara.
 */
class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // `=== false` y no `!`: un modelo recien creado en memoria no trae la
        // columna (el default lo pone MySQL) y no debe leerse como inactivo.
        if ($user && $user->activo === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu usuario está desactivado. Consulta con el administrador.',
            ]);
        }

        return $next($request);
    }
}
