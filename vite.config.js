import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    server: {
        host: "127.0.0.1",
        hmr: { host: "127.0.0.1", protocol: "ws" },
        watch: {
            ignored: ["**/storage/**", "**/vendor/**", "**/public/build/**", "**/*.jsonl", "**/*.log"],
        },
    },
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/js/exercises/index.js",
                "resources/js/reflection-recorder.js"
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
