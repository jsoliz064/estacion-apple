<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="setup()" :class="{ 'dark': isDark }">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />


    <script>
        if (localStorage.getItem('dark') === null) {
            localStorage.setItem('dark', 'false');
        }
        if (localStorage.getItem('dark') === 'false') {
            document.documentElement.classList.remove('dark');
        }
    </script>

    @stack('css')

    <!-- Font Awesome -->
    {{-- La misma version que servia el kit (6.7.2, free, con los alias de v4),
         pero desde cdnjs: el kit depende de una cuenta de Font Awesome con su
         cuota de visitas y sus dominios permitidos, y cuando deja de servir
         desaparecen todos los iconos sin ningun error en la app. --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/v4-shims.min.css"
        integrity="sha512-U+fiq69HDM4etLVUiZeQxmJE5AZoft4ti4TIM95MqXV0IjBXgT2oHw5cNeIFqz3OE/axTKQIR8e7zlm3xbOVmg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Prevent light theme flash */
        :root {
            color-scheme: light dark;
        }

        /* Dark theme classes */
        .dark .dark\:divide-gray-700> :not([hidden])~ :not([hidden]) {
            border-color: rgba(55, 65, 81);
        }

        .dark .dark\:bg-gray-50 {
            background-color: rgba(249, 250, 251);
        }

        .dark .dark\:bg-gray-100 {
            background-color: rgba(243, 244, 246);
        }

        .dark .dark\:bg-gray-600 {
            background-color: rgba(75, 85, 99);
        }

        .dark .dark\:bg-gray-700 {
            background-color: rgba(55, 65, 81);
        }

        .dark .dark\:bg-gray-800 {
            background-color: rgba(31, 41, 55);
        }

        .dark .dark\:bg-gray-900 {
            background-color: rgba(17, 24, 39);
        }

        .dark .dark\:bg-red-700 {
            background-color: rgba(185, 28, 28);
        }

        .dark .dark\:bg-green-700 {
            background-color: rgba(4, 120, 87);
        }

        .dark .dark\:hover\:bg-gray-200:hover {
            background-color: rgba(229, 231, 235);
        }

        .dark .dark\:hover\:bg-gray-600:hover {
            background-color: rgba(75, 85, 99);
        }

        .dark .dark\:hover\:bg-gray-700:hover {
            background-color: rgba(55, 65, 81);
        }

        .dark .dark\:hover\:bg-gray-900:hover {
            background-color: rgba(17, 24, 39);
        }

        .dark .dark\:border-gray-100 {
            border-color: rgba(243, 244, 246);
        }

        .dark .dark\:border-gray-400 {
            border-color: rgba(156, 163, 175);
        }

        .dark .dark\:border-gray-500 {
            border-color: rgba(107, 114, 128);
        }

        .dark .dark\:border-gray-600 {
            border-color: rgba(75, 85, 99);
        }

        .dark .dark\:border-gray-700 {
            border-color: rgba(55, 65, 81);
        }

        .dark .dark\:border-gray-900 {
            border-color: rgba(17, 24, 39);
        }

        .dark .dark\:hover\:border-gray-800:hover {
            border-color: rgba(31, 41, 55);
        }

        .dark .dark\:text-white {
            color: rgba(255, 255, 255);
        }

        .dark .dark\:text-gray-50 {
            color: rgba(249, 250, 251);
        }

        .dark .dark\:text-gray-100 {
            color: rgba(243, 244, 246);
        }

        .dark .dark\:text-gray-200 {
            color: rgba(229, 231, 235);
        }

        .dark .dark\:text-gray-400 {
            color: rgba(156, 163, 175);
        }

        .dark .dark\:text-gray-500 {
            color: rgba(107, 114, 128);
        }

        .dark .dark\:text-gray-700 {
            color: rgba(55, 65, 81);
        }

        .dark .dark\:text-gray-800 {
            color: rgba(31, 41, 55);
        }

        .dark .dark\:text-red-100 {
            color: rgba(254, 226, 226);
        }

        .dark .dark\:text-green-100 {
            color: rgba(209, 250, 229);
        }

        .dark .dark\:text-brand-400 {
            color: rgba(127, 159, 113);
        }

        .dark .group:hover .dark\:group-hover\:text-gray-500 {
            color: rgba(107, 114, 128);
        }

        .dark .group:focus .dark\:group-focus\:text-gray-700 {
            color: rgba(55, 65, 81);
        }

        .dark .dark\:hover\:text-gray-100:hover {
            color: rgba(243, 244, 246);
        }

        .dark .dark\:hover\:text-brand-500:hover {
            color: rgba(95, 131, 82);
        }

        /* Custom style */
        .header-right {
            width: calc(100% - 4.5rem); /* Ajustado ligeramente para el header en móvil */
        }

        .sidebar:hover {
            width: 16rem;
        }

        @media only screen and (min-width: 768px) {
            .header-right {
                width: calc(100% - 16rem);
            }
        }
    </style>
    <style>
        /* Custom styles for submenu */
        .submenu-items {
            transition: max-height 0.3s ease, opacity 0.2s ease;
            opacity: 0;
        }

        .submenu-container.open .submenu-items {
            max-height: 500px;
            /* Adjust based on content height */
            opacity: 1;
        }

        .submenu-container.open .submenu-arrow {
            transform: rotate(180deg);
        }
    </style>
    <style>
        .table-container {
            overflow-x: auto;
            overflow-y: visible;
            contain: none;
        }

        [x-show='open']::-webkit-scrollbar {
            width: 6px;
        }

        [x-show='open']::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }

        body.dropdown-open {
            overflow-y: auto !important;
        }
    </style>

    @livewireStyles
</head>

<body>

    <div x-data="setup()" :class="{ 'dark': isDark }">
        <div
            class="min-h-screen flex flex-col flex-auto flex-shrink-0 antialiased bg-brand-50 dark:bg-gray-700 text-black dark:text-white">
            <!-- Header -->
            <div class="fixed w-full flex items-center justify-between h-14 text-gray-900 dark:text-white z-20">
                <div
                    class="flex items-center justify-start md:justify-center pl-3 w-[4.5rem] md:w-64 h-14 bg-brand-300 dark:bg-gray-800 border-none transition-all duration-300">
                    <!-- Hamburger Button (All devices) -->
                    <button @click="toggleSidebar" class="mr-2 focus:outline-none hover:text-gray-500 dark:hover:text-gray-300">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    <img class="w-7 h-7 md:w-10 md:h-10 mr-2 rounded-md overflow-hidden"
                        src="{{ asset('imgs/logo-mark.png') }}" />
                    <span class="hidden md:block">Estación Apple</span>
                </div>
                <div class="flex justify-end items-center h-14 bg-brand-300 dark:bg-gray-800 header-right px-4">
                    <ul class="flex items-center space-x-4">
                        <li>
                            <button aria-hidden="true" @click="toggleTheme"
                                class="group p-2 transition-colors duration-200 rounded-full shadow-md bg-white hover:bg-brand-100 dark:bg-gray-50 dark:hover:bg-gray-200 text-gray-900 focus:outline-none">
                                <svg x-show="isDark" width="18" height="18" class="fill-current text-gray-700"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                                <svg x-show="!isDark" width="18" height="18" class="fill-current text-gray-700"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                            </button>
                        </li>
                        <li>
                            <div class="block w-px h-6 bg-gray-400 dark:bg-gray-700"></div>
                        </li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex items-center hover:text-brand-500 dark:hover:text-brand-100">
                                    <span class="inline-flex mr-1">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                                            </path>
                                        </svg>
                                    </span>
                                    Salir
                                </button>
                            </form>
                        </li>

                    </ul>
                </div>
            </div>
            <!-- ./Header -->
            
            <!-- Mobile Overlay -->
            <div x-show="isSidebarOpen" @click="isSidebarOpen = false" x-transition.opacity 
                class="fixed inset-0 bg-black/50 z-20 md:hidden" style="display: none;"></div>

            <!-- Sidebar -->
            <div
                :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed flex flex-col top-14 left-0 w-64 bg-brand-200 dark:bg-gray-900 h-full text-gray-900 dark:text-white transition-all duration-300 border-none z-30 sidebar">
                <div class="overflow-y-auto overflow-x-hidden flex flex-col justify-between flex-grow">
                    <ul class="flex flex-col py-4 space-y-1">
                        <!-- Existing Inicio link -->
                        @can('dashboard.index')
                            <li>
                                <a href="{{ route('welcome') }}"
                                    class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                    <span class="inline-flex justify-center items-center ml-4">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                                            </path>
                                        </svg>
                                    </span>
                                    <span class="ml-2 text-sm tracking-wide truncate">Inicio</span>
                                </a>
                            </li>
                        @endcan


                        <!-- Administración Submenu -->
                        <li class="relative submenu-container">
                            <button
                                class="submenu-toggle relative flex flex-row items-center w-full h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                <span class="inline-flex justify-center items-center ml-4">
                                    <i class="fas fa-user-cog text-lg w-5 h-5 flex items-center justify-center"></i>
                                </span>
                                <span class="ml-2 text-sm tracking-wide truncate">Administración</span>
                                <span class="submenu-arrow ml-auto mr-4 transition-transform duration-300">
                                    <svg class="w-4 h-4 ml-2 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7" />
                                    </svg>
                                </span>
                            </button>

                            <!-- Submenu Items -->
                            <ul
                                class="submenu-items space-y-1 overflow-hidden bg-brand-100 dark:bg-gray-800 transition-all duration-300 max-h-0">
                                @can('user.index')
                                    <li>
                                        <a href="{{ route('users') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i class="fas fa-user text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Usuarios</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('cliente.index')
                                    <li>
                                        <a href="{{ route('clientes') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-users text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Clientes</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('proveedor.index')
                                    <li>
                                        <a href="{{ route('proveedores') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-truck-field text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Proveedores</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('tecnico.index')
                                    <li>
                                        <a href="{{ route('tecnicos') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-wrench text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Técnicos</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('sucursal.index')
                                    <li>
                                        <a href="{{ route('sucursales') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-store text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Sucursales</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('metodo-pago.index')
                                    <li>
                                        <a href="{{ route('metodos-pago') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-credit-card text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Métodos de pago</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('rol.index')
                                    <li>
                                        <a href="{{ route('roles') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fas fa-shield-halved text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Roles</span>
                                        </a>
                                    </li>
                                @endcan

                            </ul>
                        </li>

                        <!-- Inventario Submenu -->
                        <li class="relative submenu-container">
                            <button
                                class="submenu-toggle relative flex flex-row items-center w-full h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                <span class="inline-flex justify-center items-center ml-4">
                                    <i
                                        class="fa-solid fa-boxes-stacked text-lg w-5 h-5 flex items-center justify-center"></i>
                                </span>
                                <span class="ml-2 text-sm tracking-wide truncate">Inventario</span>
                                <span class="submenu-arrow ml-auto mr-4 transition-transform duration-300">
                                    <svg class="w-4 h-4 ml-2 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7" />
                                    </svg>
                                </span>
                            </button>

                            <!-- Submenu Items -->
                            <ul
                                class="submenu-items space-y-1 overflow-hidden bg-brand-100 dark:bg-gray-800 transition-all duration-300 max-h-0">
                                @can('producto.index')
                                    <li>
                                        <a href="{{ route('productos') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-mobile-screen-button text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Productos</span>
                                        </a>
                                    </li>
                                @endcan
                                {{-- Dos entradas hermanas, una por ruta. Y marca de activo:
                                     este layout no la tiene en ninguna entrada, lo que con
                                     una sola entrada era cosmetico y con dos hermanas es
                                     confuso -- sobre todo porque el submenu arranca
                                     colapsado y no se ve de donde vienes. --}}
                                @can('repuesto.index')
                                    <li>
                                        <a href="{{ route('repuestos') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group {{ request()->routeIs('repuestos') ? 'bg-brand-300 dark:bg-gray-700 font-semibold' : '' }}">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-screwdriver text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Repuestos</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('accesorio.index')
                                    <li>
                                        <a href="{{ route('accesorios') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group {{ request()->routeIs('accesorios') ? 'bg-brand-300 dark:bg-gray-700 font-semibold' : '' }}">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-headphones text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Accesorios</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('codigo.index')
                                    <li>
                                        <a href="{{ route('codigos') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group {{ request()->routeIs('codigos') ? 'bg-brand-300 dark:bg-gray-700 font-semibold' : '' }}">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-barcode text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Códigos de barras</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('repuesto-categoria.index')
                                    <li>
                                        <a href="{{ route('repuestos.categorias') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-list text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Categorías Repuestos</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('accesorio-categoria.index')
                                    <li>
                                        <a href="{{ route('accesorios.categorias') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-tags text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Categorías Accesorios</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('producto-modelo.index')
                                    <li>
                                        <a href="{{ route('productos.modelos') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fas fa-tags text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Modelos</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('producto-categoria.index')
                                    <li>
                                        <a href="{{ route('productos.categorias') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-solid fa-list text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Categorias</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('producto-marca.index')
                                    <li>
                                        <a href="{{ route('productos.marcas') }}"
                                            class="relative flex items-center h-10 pl-6 pr-6 text-sm hover:bg-brand-300 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                            <span class="inline-flex justify-center items-center ml-4">
                                                <i
                                                    class="fa-brands fa-apple text-lg w-5 h-5 flex items-center justify-center"></i>
                                            </span>
                                            <span class="ml-2 tracking-wide truncate">Marcas</span>
                                        </a>
                                    </li>
                                @endcan

                            </ul>
                        </li>

                        @can('compra.index')
                            <li>
                                <a href="{{ route('compras') }}"
                                    class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                    <span class="inline-flex justify-center items-center ml-4">
                                        <i class="fa-solid fa-shop text-lg w-5 h-5 flex items-center justify-center"></i>
                                    </span>
                                    <span class="ml-2 text-sm tracking-wide truncate">Compras</span>
                                </a>
                            </li>
                        @endcan

                        @can('cuenta-pagar.index')
                            <li>
                                <a href="{{ route('cuentas-por-pagar') }}"
                                    class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                    <span class="inline-flex justify-center items-center ml-4">
                                        <i class="fa-solid fa-file-invoice-dollar text-lg w-5 h-5 flex items-center justify-center"></i>
                                    </span>
                                    <span class="ml-2 text-sm tracking-wide truncate">Cuentas por pagar</span>
                                </a>
                            </li>
                        @endcan

                        @can('venta.index')
                            <li>
                                <a href="{{ route('ventas') }}"
                                    class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                    <span class="inline-flex justify-center items-center ml-4">
                                        <i class="fas fa-cash-register text-lg w-5 h-5 flex items-center justify-center"></i>
                                    </span>
                                    <span class="ml-2 text-sm tracking-wide truncate">Ventas</span>
                                </a>
                            </li>
                        @endcan

                        @can('cobranza.index')
                            <li>
                                <a href="{{ route('cobranzas') }}"
                                    class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                    <span class="inline-flex justify-center items-center ml-4">
                                        <i class="fa-solid fa-hand-holding-dollar text-lg w-5 h-5 flex items-center justify-center"></i>
                                    </span>
                                    <span class="ml-2 text-sm tracking-wide truncate">Cobranzas</span>
                                </a>
                            </li>
                        @endcan

                        @can('reserva.index')
                            <li>
                                <a href="{{ route('reservas') }}"
                                    class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                    <span class="inline-flex justify-center items-center ml-4">
                                        <i class="fa-solid fa-bookmark text-lg w-5 h-5 flex items-center justify-center"></i>
                                    </span>
                                    <span class="ml-2 text-sm tracking-wide truncate">Reservas</span>
                                </a>
                            </li>
                        @endcan

                        {{-- Al primer reporte que pueda abrir: cada uno tiene su permiso. --}}
                        @php $rutaReportes = \App\Http\Controllers\ReporteController::primeraRuta(); @endphp
                        @if ($rutaReportes)
                            <li>
                                <a href="{{ route($rutaReportes) }}"
                                    class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                    <span class="inline-flex justify-center items-center ml-4">
                                        <i class="fa-solid fa-chart-simple"></i>
                                    </span>
                                    <span class="ml-2 text-sm tracking-wide truncate">Reportes</span>
                                </a>
                            </li>
                        @endif

                        @can('comision.index')
                            <li>
                                <a href="{{ route('comisiones') }}"
                                    class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                    <span class="inline-flex justify-center items-center ml-4">
                                        <i class="fa-solid fa-percent text-lg w-5 h-5 flex items-center justify-center"></i>
                                    </span>
                                    <span class="ml-2 text-sm tracking-wide truncate">Comisiones</span>
                                </a>
                            </li>
                        @endcan

                        @can('comision.propias')
                            <li>
                                <a href="{{ route('mis-comisiones') }}"
                                    class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                    <span class="inline-flex justify-center items-center ml-4">
                                        <i class="fa-solid fa-wallet text-lg w-5 h-5 flex items-center justify-center"></i>
                                    </span>
                                    <span class="ml-2 text-sm tracking-wide truncate">Mis comisiones</span>
                                </a>
                            </li>
                        @endcan


                        <li>
                            <a href="{{ route('catalogo') }}" target="_blank"
                                class="relative flex flex-row items-center h-11 focus:outline-none hover:bg-brand-300 dark:hover:bg-gray-600 text-white-600 hover:text-white-800 border-l-4 border-transparent hover:border-brand-800 dark:hover:border-gray-800 pr-6">
                                <span class="inline-flex justify-center items-center ml-4">
                                    <i class="fas fa-shopping-bag"></i>
                                </span>
                                <span class="ml-2 text-sm tracking-wide truncate">Catálogo</span>
                            </a>
                        </li>

                    </ul>
                    <p class="mb-14 px-5 py-3 hidden md:block text-center text-xs">Estación Apple © 2026</p>
                </div>
            </div>

            <!-- Main Content Area -->
            <main :class="isSidebarOpen ? 'md:ml-64' : ''" class="flex-1 transition-all duration-300 mt-14 ml-0 mb-10">
                <div class="p-6 h-full overflow-auto">
                    {{ $slot }}
                </div>
            </main>

        </div>
    </div>

    <x-escaner-overlay />
    <x-camara-fotos />

    @livewireScripts

    <script>
        const setup = () => {
            const getTheme = () => {
                if (localStorage.getItem('dark') === null) {
                    return false; // Default to light theme
                }
                return localStorage.getItem('dark') === 'true';
            };

            const setTheme = (value) => {
                localStorage.setItem('dark', value);
                if (value) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            };

            const initialTheme = getTheme();
            setTheme(initialTheme);

            return {
                loading: true,
                isDark: initialTheme,
                isSidebarOpen: window.innerWidth >= 768,
                toggleSidebar() {
                    this.isSidebarOpen = !this.isSidebarOpen;
                },
                toggleTheme() {
                    this.isDark = !this.isDark;
                    setTheme(this.isDark);
                },
            };
        };
    </script>
    @stack('js')

    @if (session('swal'))
        <script>
            Swal.fire({!! json_encode(session('swal')) !!});
        </script>
    @endif

    <script>
        Livewire.on('swal', data => {
            Swal.fire(data[0]);
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.submenu-toggle');

            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const container = this.closest('.submenu-container');
                    container.classList.toggle('open');

                    // Close other open submenus if needed
                    document.querySelectorAll('.submenu-container').forEach(item => {
                        if (item !== container && item.classList.contains('open')) {
                            item.classList.remove('open');
                        }
                    });
                });
            });
        });
    </script>

</body>

</html>
