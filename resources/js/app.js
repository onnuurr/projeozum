import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
// Components/ kiti (StatWidget, AppBadge, QuickActionWidget vb.) <UiIcon name="..."> kullanıyor,
// global registration bekliyor — yoksa "failed to resolve component" ile sessizce hiç render olmuyor.
import UiIcon from './Components/UiIcon.vue';

const fallbackAppName = import.meta.env.VITE_APP_NAME || 'Laravel';

// App adı server'dan Inertia shared props (`app.name`) ile gelir;
// her navigasyonda güncellenir, böylece DB'deki systemName değişince
// rebuild gerekmeden yeni değer geçerli olur.
let currentAppName = fallbackAppName;
router.on('success', (event) => {
    const sharedAppName = event.detail.page?.props?.app?.name;
    if (typeof sharedAppName === 'string' && sharedAppName !== '') {
        currentAppName = sharedAppName;
    }
});

const appPages = import.meta.glob('./Pages/**/*.vue');
const modulePages = import.meta.glob('../../Modules/*/Resources/assets/js/Pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} - ${currentAppName}` : currentAppName),
    resolve: (name) => {
        if (name.includes('::')) {
            const [module, page] = name.split('::');
            const path = `../../Modules/${module}/Resources/assets/js/Pages/${page}.vue`;
            return resolvePageComponent(path, modulePages);
        }
        return resolvePageComponent(`./Pages/${name}.vue`, appPages);
    },
    setup({ el, App, props, plugin }) {
        // İlk render'da router 'success' event'i henüz tetiklenmemiş olabilir.
        const initialAppName = props.initialPage?.props?.app?.name;
        if (typeof initialAppName === 'string' && initialAppName !== '') {
            currentAppName = initialAppName;
        }

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('UiIcon', UiIcon)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
