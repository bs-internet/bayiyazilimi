import { createInertiaApp } from '@inertiajs/react';
import '@/extensions';
import { layout, resolve, title, withApp } from '@/inertia-config';

// Sunucu tarafı render girişi. @inertiajs/vite bu çağrıyı SSR derlemesinde
// createServer + renderToString ile sarar (`npm run build:ssr`,
// `php artisan inertia:start-ssr`). Sunucu çalışmıyorsa Inertia istemci
// tarafı render'a düşer.
createInertiaApp({
    title,
    resolve,
    layout,
    withApp,
});
