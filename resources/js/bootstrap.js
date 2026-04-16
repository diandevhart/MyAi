import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

if (import.meta.env.VITE_PUSHER_APP_KEY) {
    import('pusher-js').then((Pusher) => {
        window.Pusher = Pusher.default;

        import('laravel-echo').then((Echo) => {
            window.Echo = new Echo.default({
                broadcaster: 'pusher',
                key: import.meta.env.VITE_PUSHER_APP_KEY,
                cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
                forceTLS: true,
                encrypted: true,
                disableStats: true,
                enabledTransports: ['ws', 'wss'],
            });
        });
    });
}