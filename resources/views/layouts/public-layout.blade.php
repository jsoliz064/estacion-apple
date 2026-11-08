@php
    $tituloPagina = $titulo ? $titulo . ' · Estación Apple' : config('app.name');
    $descripcionPagina = $descripcion ?? 'Estación Apple: iPhone nuevos, seminuevos y open box.';
    $imagenPagina = $imagen ?? asset('imgs/portada.webp');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="setup()" :class="{ 'dark': isDark }">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $tituloPagina }}</title>
    <meta name="description" content="{{ $descripcionPagina }}">
    <meta name="theme-color" content="#111827">
    {{-- La vista previa al compartir el link (WhatsApp, Facebook). --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Estación Apple">
    <meta property="og:title" content="{{ $tituloPagina }}">
    <meta property="og:description" content="{{ $descripcionPagina }}">
    <meta property="og:image" content="{{ $imagenPagina }}">
    <meta property="og:url" content="{{ url()->full() }}">

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <script>
        if (localStorage.getItem('dark') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>

    @stack('css')

    {{-- La misma version que el layout principal, desde cdnjs. --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased">
    {{-- Barra simple: antes copiaba la forma del panel (w-14 / md:w-64 de la
         barra lateral), que en una pagina publica no tiene sentido. --}}
    <header class="sticky top-0 z-30 h-14 bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b border-gray-200 dark:border-gray-800">
        <div class="mx-auto flex h-full max-w-7xl items-center justify-between px-4">
            <a href="{{ route('catalogo') }}" class="flex items-center gap-2">
                <img src="{{ asset('imgs/logo-mark.png') }}" alt="" class="h-8 w-8 rounded-full">
                <span class="text-sm font-bold tracking-wide">ESTACIÓN APPLE</span>
            </a>
            <button type="button" @click="toggleTheme" aria-label="Cambiar tema"
                class="flex h-9 w-9 items-center justify-center rounded-full text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                <i class="fa-solid fa-moon" x-show="!isDark"></i>
                <i class="fa-solid fa-sun" x-show="isDark" x-cloak></i>
            </button>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    @livewireScripts

    <script>
        const setup = () => {
            const isDark = localStorage.getItem('dark') === 'true';

            return {
                isDark,
                toggleTheme() {
                    this.isDark = !this.isDark;
                    localStorage.setItem('dark', this.isDark);
                    document.documentElement.classList.toggle('dark', this.isDark);
                },
            };
        };
    </script>
    @stack('js')
</body>

</html>
