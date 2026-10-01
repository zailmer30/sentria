import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import '../css/app.css';

const appName = import.meta.env.VITE_APP_NAME || 'Sentria';

function inertiaProgressColor(): string {
    if (typeof window === 'undefined') {
        return '#0038a8';
    }

    const value = window.getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim();

    return value === '' ? '#0038a8' : value;
}

createInertiaApp({
    title: (title) => (title ? title : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: inertiaProgressColor(),
    },
});
