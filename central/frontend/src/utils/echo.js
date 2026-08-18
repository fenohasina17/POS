import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

const env = window.__ENV__ ?? import.meta.env

const echo = new Echo({
  broadcaster: 'reverb',
  key:      env.VITE_REVERB_APP_KEY,
  wsHost:   env.VITE_REVERB_HOST,
  wsPort:   env.VITE_REVERB_PORT ?? 80,
  wssPort:  env.VITE_REVERB_PORT ?? 443,
  forceTLS: (env.VITE_REVERB_SCHEME ?? 'ws') === 'https',
  enabledTransports: ['ws', 'wss'],
  debug: import.meta.env.DEV,
})

window.Echo = echo
export default echo
