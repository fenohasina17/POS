import { computed, ref } from 'vue'

// État partagé au niveau du module — un seul flux de notifications pour toute
// l'app (Dashboard.vue est le shell persistant), pas de persistance au-delà
// de la session : un rechargement vide juste ce badge, le catalogue local
// reste correct quoi qu'il arrive.
const notifications = ref([])
const unreadCount = ref(0)
let subscribed = false

function subscribe() {
  if (subscribed || !window.Echo) return
  subscribed = true

  // Pas de point devant le nom d'événement : ProductCatalogUpdated n'a pas
  // de broadcastAs() custom, contrairement à TableLockUpdated.
  window.Echo.channel('catalog').listen('ProductCatalogUpdated', (e) => {
    notifications.value.unshift({
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
      created: e.created?.length ?? 0,
      updated: e.updated?.length ?? 0,
      at: new Date(),
    })
    unreadCount.value++
  })
}

function unsubscribe() {
  if (!subscribed) return
  window.Echo?.leaveChannel('catalog')
  subscribed = false
}

function markAllRead() {
  unreadCount.value = 0
}

export function useCatalogNotifications() {
  return {
    notifications: computed(() => notifications.value),
    unreadCount: computed(() => unreadCount.value),
    subscribe,
    unsubscribe,
    markAllRead,
  }
}
