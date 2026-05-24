import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfMeta = document.head.querySelector('meta[name="csrf-token"]');
if (csrfMeta) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.content;
}

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Pusher strategy override - wss fallback'i devre dışı bırak
const OriginalPusher = Pusher;
Pusher.getGlobalConfig = function() {
    return {
        wsHost: '127.0.0.1',
        wsPort: 8080,
        wssPort: 8080,
        httpHost: '127.0.0.1',
        httpPort: 8080,
        httpsPort: 8080,
        scheme: 'http',
        useTLS: false,
        useSockjs: false,
        enabledTransports: ['ws'],
        disabledTransports: ['wss', 'sockjs'],
    };
};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'nmd655cwaniqz6omotn2',
    wsHost: '127.0.0.1',
    wsPort: 8080,
    wssPort: 8080,
    forceTLS: false,
    useTLS: false,
    enabledTransports: ['ws'],
});