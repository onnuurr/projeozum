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

// Reverb bağlantı bilgileri .env'deki VITE_REVERB_* değerlerinden okunur (yerelde
// tanımsızsa eski davranış: 127.0.0.1:8080/http). Böylece aynı kod hem yerel geliştirmede
// hem prodüksiyonda (gerçek domain + wss, nginx'in /app/ location'ı üzerinden Reverb'e
// proxy'lenir) doğru çalışır.
const reverbHost   = import.meta.env.VITE_REVERB_HOST || '127.0.0.1';
const reverbPort   = Number(import.meta.env.VITE_REVERB_PORT) || 8080;
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
const reverbTLS    = reverbScheme === 'https';

// Pusher strategy override - wss fallback'i devre dışı bırak
Pusher.getGlobalConfig = function() {
    return {
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        httpHost: reverbHost,
        httpPort: reverbPort,
        httpsPort: reverbPort,
        scheme: reverbScheme,
        useTLS: reverbTLS,
        useSockjs: false,
        enabledTransports: reverbTLS ? ['wss'] : ['ws'],
        disabledTransports: reverbTLS ? ['ws', 'sockjs'] : ['wss', 'sockjs'],
    };
};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'nmd655cwaniqz6omotn2',
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbTLS,
    useTLS: reverbTLS,
    enabledTransports: reverbTLS ? ['wss'] : ['ws'],
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