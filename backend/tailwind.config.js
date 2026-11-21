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
                // FreightPath teal (from the logo, #0B6E6E = 600).
                brand: {
                    50: "#E7F5F5",
                    100: "#C7E9E9",
                    200: "#94D3D3",
                    300: "#5BB8B8",
                    400: "#2A9A9A",
                    500: "#0F8282",
                    600: "#0B6E6E",
                    700: "#095A5A",
                    800: "#084848",
                    900: "#063838",
                },
                // FreightPath amber (the logo's route and "PATH", #FFB020 = 400).
                accent: {
                    50: "#FFF8E6",
                    100: "#FFEDC2",
                    200: "#FFDB85",
                    300: "#FFC94D",
                    400: "#FFB020",
                    500: "#E89B0C",
                    600: "#C27C06",
                    700: "#9A6108",
                },
            },
        },
    },

    plugins: [forms],
};
