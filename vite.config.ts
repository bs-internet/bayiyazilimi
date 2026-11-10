import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

export default defineConfig({
    resolve: {
        alias: {
            // Eklenti / tema paketleri birbirinin ön yüz kodunu bu takma adla
            // içe aktarır (ör. bir tema, ön yüz eklentisinin yardımcılarını):
            // '@modules/vitrin/resources/js/...' → vendor/bsinternet/vitrin/...
            '@modules': fileURLToPath(
                new URL('./vendor/bsinternet', import.meta.url),
            ),
        },
    },
    server: {
        fs: {
            // Modüller kardeş klasörlerde geliştirilir ve vendor/bsinternet/* içine
            // symlink'lenir. Vite symlink'i gerçek yola çözdüğü için hedef proje
            // kökünün dışında kalır; okumaya izin verilmezse dev sunucu engeller.
            allow: ['..'],
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            // Sunucu tarafı render (vitrin SEO'su): npm run build:ssr → bootstrap/ssr
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
            fonts: [
                // latin-ext: Türkçe ğ, ş, İ, ı karakterleri (yalnızca "latin"
                // alt kümesiyle bu harfler sistem fontuna düşüyordu).
                // 700: vitrindeki kalın başlık/fiyatlar sahte kalınlaşmasın.
                bunny('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                    subsets: ['latin', 'latin-ext'],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
