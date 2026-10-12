import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
import typography from "@tailwindcss/typography";

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./vendor/laravel/jetstream/**/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.js",
        // Clases armadas en PHP: badges de los enums y ->format() de las tablas
        // de rappasoft. Sin esta linea solo se compilaban si por casualidad
        // aparecian tambien en algun blade.
        "./app/**/*.php",
        //Datatables
        "./vendor/rappasoft/laravel-livewire-tables/resources/views/**/*.blade.php",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // El color de la marca sale de variables CSS (resources/css/app.css):
                // grises neutros en modo claro, como el logo de Estacion Apple, y el
                // verde salvia heredado en modo oscuro, que se dejo como estaba. Con
                // un hex fijo aqui, cambiar el claro cambiaba tambien los ~130
                // `dark:*-brand-*` y los botones que no tienen variante dark.
                // Tiene nombre propio y no reutiliza gray-* ni green-*: gray-* pinta
                // las superficies del modo oscuro y green-* significa "exito /
                // disponible" en todo el sistema.
                brand: Object.fromEntries(
                    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((n) => [
                        n,
                        `rgb(var(--brand-${n}) / <alpha-value>)`,
                    ])
                ),
                // Melocoton: acento secundario (badge de Venta externa, total de
                // la compra).
                desert: {
                    50: "#fcf8f3",
                    100: "#f8eee2",
                    200: "#f1dcc4",
                    300: "#e9cfae",
                    400: "#dcb07f",
                    500: "#d0955a",
                    600: "#c27c47",
                    700: "#a1623c",
                    800: "#825036",
                    900: "#69432f",
                },
            },
        },
    },

    plugins: [forms, typography],
};
