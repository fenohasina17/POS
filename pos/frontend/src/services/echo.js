import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// window.__ENV__ est injecté au démarrage du container via env-config.js
// import.meta.env sert de fallback pour le développement local (npm run dev)
const env = window.__ENV__ ?? import.meta.env

const echo = new Echo({
    broadcaster: 'reverb',
    key: env.VITE_REVERB_APP_KEY,
    wsHost: env.VITE_REVERB_HOST,
    wsPort: env.VITE_REVERB_PORT ?? 80,
    wssPort: env.VITE_REVERB_PORT ?? 443,
    forceTLS: (env.VITE_REVERB_SCHEME ?? 'ws') === 'https',
    enabledTransports: ['ws', 'wss'],
    debug: import.meta.env.DEV,
});

window.Echo = echo;

echo.connector.pusher.connection.bind('error', (err) => {
    console.error('Erreur de connexion WebSocket:', err);
});

export default echo;
