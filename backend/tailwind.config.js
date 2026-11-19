import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Inter", "Figtree", ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: "#FFF5EA",
                    100: "#FFE8D0",
                    200: "#FFD0A1",
                    300: "#FFB46B",
                    400: "#FF9A3D",
                    500: "#FF8A1F",
                    600: "#F27A0A",
                    700: "#C96205",
                    800: "#9C4C08",
                    900: "#7A3D0B",
                },
            },
        },
    },

    plugins: [forms],
};
