<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config("app.name") }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles

    <style>
        @keyframes logoReveal {
            0% {
                transform: translate(-50%, -50%) scale(0) rotate(0deg);
                opacity: 0;
            }

            60% {
                transform: translate(-50%, -50%) scale(1.1) rotate(190deg);
                opacity: 1;
            }

            80% {
                transform: translate(-50%, -50%) scale(0.95) rotate(355deg);
            }

            100% {
                transform: translate(-50%, -50%) scale(1) rotate(360deg);
                opacity: 1;
            }
        }

        @keyframes gradientExpand {
            0% {
                clip-path: circle(0% at 50% 50%);
                background-size: 200% 200%;
            }

            100% {
                clip-path: circle(100% at 50% 50%);
                background-size: 150% 150%;
            }
        }

        @keyframes textFadeIn {
            0% {
                opacity: 0;
                transform: translateY(10px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes formSlideUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .header-bg {
            background: linear-gradient(135deg, #2a3a26 0%, #4a6a40 50%, #2a3a26 100%);
            animation: gradientExpand 1.2s cubic-bezier(0.65, 0, 0.35, 1) forwards;
            clip-path: circle(0% at 50% 0);
            background-size: 200% 200%;
        }

        .logo-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: logoReveal 1.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        .brand-text {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0;
            animation: textFadeIn 0.8s ease-out forwards 1.2s;
        }

        .brand-name {
            font-size: 28px;
            line-height: 1;
        }

        .brand-subtitle {
            font-size: 14px;
            letter-spacing: 4px;
            margin-top: 4px;
            opacity: 0.8;
        }

        .login-form {
            opacity: 0;
            animation: formSlideUp 1s ease-out forwards 1.5s;
        }

        .ripple {
            position: absolute;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }

        .floating {
            animation: float 4s ease-in-out infinite;
        }

        .input-highlight {
            transition: all 0.3s ease;
        }

        .input-field:focus+.input-highlight {
            transform: scaleX(1);
            opacity: 1;
        }

        .btn-morph {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-origin: center;
            background: linear-gradient(to right, #3c5534, #4a6a40);
        }

        .btn-morph:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
            background: linear-gradient(to right, #4a6a40, #3c5534);
        }

        .btn-morph:active {
            transform: translateY(1px) scale(0.98);
        }

        .checkmark {
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .checkbox:checked+.checkmark {
            transform: scale(1.1);
            background-color: #3c5534;
            border-color: #3c5534;
        }

        .checkbox:checked+.checkmark svg {
            opacity: 1;
            transform: scale(1);
        }

        .logo-image {
            width: 130px;
            height: 130px;
            object-fit: contain;
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.3));
        }
    </style>
</head>

<body class="font-sans antialiased">
    <div class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
        <!-- Floating background elements -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-1/4 left-1/4 w-16 h-16 bg-brand-900 opacity-5 rounded-full floating"
                style="animation-delay: 0s;"></div>
            <div class="absolute top-1/3 right-1/4 w-24 h-24 bg-brand-900 opacity-5 rounded-full floating"
                style="animation-delay: 0.5s;"></div>
            <div class="absolute bottom-1/4 left-1/3 w-20 h-20 bg-brand-900 opacity-5 rounded-full floating"
                style="animation-delay: 1s;"></div>
        </div>

        <div class="relative w-full max-w-md">
            <!-- Gradient header with logo and brand text -->
            <div class="header-bg relative h-56 rounded-t-2xl overflow-hidden flex items-center justify-center">
                <div class="logo-container">
                    <img src="{{ asset('imgs/logo-mark.png') }}" alt="Estación Apple" class="logo-image rounded-full">
                </div>

            </div>

            <!-- Login form -->
            <div class="login-form bg-white p-8 rounded-b-2xl shadow-xl">
                <x-validation-errors class="mb-4" />

                @if (session('status'))
                    <div class="mb-4 font-medium text-sm text-green-600">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="relative">
                        <x-label for="email" value="{{ __('Email') }}" />
                        <x-input id="email" class="input-field block mt-1 w-full" type="email" name="email"
                            :value="old('email')" required autofocus autocomplete="username" />
                        <div
                            class="input-highlight absolute bottom-0 left-0 h-0.5 bg-brand-600 transform scale-x-0 opacity-0 transition-all duration-300 w-full">
                        </div>
                    </div>

                    <div class="mt-4 relative">
                        <x-label for="password" value="{{ __('Password') }}" />
                        <x-input id="password" class="input-field block mt-1 w-full" type="password" name="password"
                            required autocomplete="current-password" />
                        <div
                            class="input-highlight absolute bottom-0 left-0 h-0.5 bg-brand-600 transform scale-x-0 opacity-0 transition-all duration-300 w-full">
                        </div>
                    </div>



                    <div class="flex items-center justify-end mt-4">


                        <button type="submit"
                            class="btn-morph ms-4 py-2 px-4 text-white rounded-lg font-medium relative overflow-hidden shadow-md">
                            {{ __('Log in') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @livewireScripts
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ripple effect for login button
            const loginBtn = document.querySelector('.btn-morph');

            if (loginBtn) {
                loginBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    const ripple = document.createElement('span');
                    ripple.classList.add('ripple');
                    this.appendChild(ripple);

                    const rect = this.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;

                    ripple.style.left = `${x}px`;
                    ripple.style.top = `${y}px`;

                    setTimeout(() => {
                        ripple.remove();
                        this.closest('form').submit();
                    }, 600);
                });
            }

            // Custom checkbox interaction
            document.querySelectorAll('.checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const checkmark = this.nextElementSibling;
                    if (this.checked) {
                        checkmark.classList.add('bg-brand-700', 'border-brand-700');
                        checkmark.querySelector('svg').classList.remove('opacity-0', 'scale-0');
                    } else {
                        checkmark.classList.remove('bg-brand-700', 'border-brand-700');
                        checkmark.querySelector('svg').classList.add('opacity-0', 'scale-0');
                    }
                });
            });

            // Input field focus effects
            const inputs = document.querySelectorAll('.input-field');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('.input-highlight').classList.add(
                        'opacity-100', 'scale-x-100');
                });

                input.addEventListener('blur', function() {
                    this.parentElement.querySelector('.input-highlight').classList.remove(
                        'opacity-100', 'scale-x-100');
                });
            });
        });
    </script>
</body>

</html>
