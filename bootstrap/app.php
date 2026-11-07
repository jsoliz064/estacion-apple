<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Saca al usuario desactivado aunque tenga la sesion abierta o la
        // cookie de "recordarme": el login ya lo rechaza, pero eso no basta.
        $middleware->web(append: [
            \App\Http\Middleware\UsuarioActivo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Un 419 de formulario (el token era de una sesion que ya no existe: la
        // pestana quedo abierta dias) vuelve a pedir la pagina en vez de mostrar
        // "Page Expired", que solo se arreglaba recargando. Llega como
        // HttpException(419): Laravel convierte el TokenMismatchException antes
        // de estos callbacks. Livewire tiene su propio aviso del 419.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson() || $request->hasHeader('X-Livewire')) {
                return null;
            }

            $mensaje = 'Tu sesión había caducado. Vuelve a intentarlo.';

            return Auth::check()
                ? back()->withInput($request->except('password', 'password_confirmation', '_token'))->with('status', $mensaje)
                : redirect()->route('login')->withInput($request->only('email'))->with('status', $mensaje);
        });
    })->create();
