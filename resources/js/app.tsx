import { createInertiaApp } from '@inertiajs/react';
import 'sonner/dist/styles.css';
import { initializeTheme } from '@/hooks/use-appearance';
import '@/extensions';
import { layout, resolve, title, withApp } from '@/inertia-config';

// Bu resolve verildiği için @inertiajs/vite kendi çözücüsünü enjekte etmez.
// Ayarlar (çözücü, düzenler — vitrin dahil) ssr.tsx ile ortak: inertia-config.tsx
createInertiaApp({
    title,
    resolve,
    layout,
    strictMode: true,
    withApp,
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
