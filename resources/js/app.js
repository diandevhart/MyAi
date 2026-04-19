import '../css/app.css';
import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';

import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import 'primeicons/primeicons.css';
import ConfirmationService from 'primevue/confirmationservice';

import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';

import VueKonva from 'vue-konva';
import { createToastflow, ToastContainer } from 'vue-toastflow';

const appName = import.meta.env.VITE_APP_NAME || 'MyAi';
const pinia = createPinia();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(VueKonva)
            .use(createToastflow({
                position: 'top-right',
                duration: 5000,
            }))
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        prefix: 'p',
                        darkModeSelector: 'light',
                        cssLayer: false,
                    },
                },
            })
            .use(pinia)
            .use(ElementPlus)
            .use(ConfirmationService);

        app.component('ToastContainer', ToastContainer);

        return app.mount(el);
    },
    progress: {
        color: '#e76f0c',
    },
});