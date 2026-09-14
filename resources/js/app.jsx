import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
    title: (title) => (title ? `${title} — SoundTix` : 'SoundTix'),
});