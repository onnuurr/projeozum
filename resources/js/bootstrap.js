import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF: statik X-CSRF-TOKEN header'ı KULLANMA. Bu bir Inertia SPA'sı; <meta csrf-token>
// yalnızca ilk tam yüklemede render olur ve login sonrası session token rotasyonunda
// bayatlar. Laravel CSRF doğrulamasında X-CSRF-TOKEN, X-XSRF-TOKEN'dan önceliklidir;
// bayat statik header taze cookie token'ını gölgeleyip web POST'larda 419 verirdi.
// Bunun yerine axios'un yerleşik mekanizması kullanılır: her yanıtta tazelenen
// XSRF-TOKEN cookie'sini okuyup X-XSRF-TOKEN header'ı olarak gönderir.
// (API route'ları Bearer token kullanır; CSRF'e tabi değildir, bu header gereksizdi.)

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
    // Varsayılan XHR authorizer YERİNE axios kullanılır — statik <meta csrf-token>
    // yerine axios'un XSRF-TOKEN cookie mekanizması devreye girer.
    // Aksi halde login sonrası session rotasyonunda /broadcasting/auth 419 verir.
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            window.axios
                .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
                .then((response) => callback(false, response.data))
                .catch((error) => callback(true, error));
        },
    }),
});