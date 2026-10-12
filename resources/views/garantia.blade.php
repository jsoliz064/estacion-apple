{{-- Los terminos de garantia, publicos: a esta pagina lleva el QR de la nota y
     del recibo PDF (App\Support\QrGarantia). El texto es el del talonario del
     negocio; se cambia editando este archivo. --}}
<x-public-layout>
    <div class="mx-auto w-full max-w-2xl px-4 pt-20 pb-10">
        <div class="text-center">
            <img src="{{ asset('imgs/logo.png') }}" alt="Estación Apple" class="mx-auto h-20 w-auto">
            <h1 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">Términos de garantía</h1>
        </div>

        <div class="mt-6 space-y-5 text-gray-700 dark:text-gray-200 leading-relaxed">
            <section class="rounded-lg border border-gray-200 dark:border-gray-600 p-4">
                <h2 class="font-semibold text-brand-800 dark:text-brand-200">Garantía a teléfonos homologados</h2>
                <p class="mt-2">La garantía es por 1 año, por la empresa establecida, para todos los teléfonos nuevos de cualquier marca.</p>
            </section>

            <section class="rounded-lg border border-gray-200 dark:border-gray-600 p-4">
                <h2 class="font-semibold text-brand-800 dark:text-brand-200">Garantía Apple (teléfonos nuevos con garantía activa)</h2>
                <p class="mt-2">
                    La garantía será cubierta por Apple o por una tienda autorizada por Apple a nivel mundial (existe una en Bolivia,
                    pero no siempre está dispuesta a cubrir la garantía). En caso de no poder tener la garantía en territorio nacional,
                    el equipo será enviado a Estados Unidos. Ese servicio tiene un costo de 100 $ (cien dólares americanos), que será
                    cubierto por la tienda y el cliente en partes iguales solo durante los 3 primeros meses de uso. Pasados los 3 meses,
                    el cliente pagará el costo total del envío.
                </p>
                <p class="mt-2">El tiempo estimado para el proceso de envío y validación de la garantía en Estados Unidos es de 30 a 40 días aproximadamente.</p>
            </section>

            <section class="rounded-lg border border-gray-200 dark:border-gray-600 p-4">
                <h2 class="font-semibold text-brand-800 dark:text-brand-200">Garantía teléfonos seminuevos</h2>
                <p class="mt-2">
                    Los equipos seminuevos cuentan con 3 meses de garantía nuestra, que se valida en caso de que el equipo tenga algún
                    desperfecto de fábrica. En ese caso, el equipo deberá ser recepcionado por nuestra tienda para una revisión en un
                    plazo de 48 horas (días hábiles), para cerciorarnos de la falla; luego será reparado por nuestro servicio técnico autorizado.
                </p>
            </section>

            <section class="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-600 dark:bg-amber-900/20">
                <h2 class="font-semibold text-amber-900 dark:text-amber-200">Importante</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>La garantía no cubre problemas de software ni el bloqueo por iCloud.</li>
                    <li>
                        La garantía queda totalmente anulada en caso de que el equipo tenga desperfectos ocasionados por humedad o golpes
                        (muchas veces no se nota físicamente, pero Apple tiene mecanismos para ver si ocurrió uno).
                    </li>
                </ul>
            </section>
        </div>
    </div>
</x-public-layout>
