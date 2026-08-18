<template>
  <div class="min-h-screen bg-gray-950 text-gray-100">
    <!-- Barre de navigation (masquée sur la page login) -->
    <nav v-if="auth.isAuthenticated" class="bg-gray-900 border-b border-gray-800 px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
        <span class="font-bold text-white tracking-wide">POS Central</span>
        <span class="text-gray-500 text-sm">— Supervision</span>
      </div>
      <div class="flex items-center gap-6 text-sm">
        <router-link to="/" class="text-gray-400 hover:text-white transition-colors">Dashboard</router-link>
        <router-link to="/terminals" class="text-gray-400 hover:text-white transition-colors">Terminaux</router-link>
        <router-link to="/terminals-manage" class="text-gray-400 hover:text-white transition-colors">Gestion</router-link>

        <!-- Badge alertes -->
        <router-link to="/" class="relative text-gray-400 hover:text-white transition-colors"
          title="Alertes actives">
          <span>Alertes</span>
          <span v-if="alerts.counts.critical > 0"
            class="absolute -top-1.5 -right-3 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center leading-none">
            {{ alerts.counts.critical }}
          </span>
          <span v-else-if="alerts.counts.warning > 0"
            class="absolute -top-1.5 -right-3 bg-yellow-500 text-gray-900 text-xs rounded-full w-4 h-4 flex items-center justify-center leading-none">
            {{ alerts.counts.warning }}
          </span>
        </router-link>

        <span class="text-gray-600">{{ currentTime }}</span>
        <div class="flex items-center gap-3 border-l border-gray-700 pl-4">
          <span class="text-gray-500 text-xs">{{ auth.user?.name }}</span>
          <button @click="handleLogout"
            class="text-xs text-gray-500 hover:text-red-400 transition-colors">
            Déconnexion
          </button>
        </div>
      </div>
    </nav>

    <main :class="auth.isAuthenticated ? 'p-6' : ''">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAlertsStore } from '@/stores/alerts'

const auth   = useAuthStore()
const alerts = useAlertsStore()
const router = useRouter()

const currentTime = ref('')
let timer, alertTimer

onMounted(async () => {
  const update = () => { currentTime.value = new Date().toLocaleTimeString('fr-FR') }
  update()
  timer = setInterval(update, 1000)

  if (auth.isAuthenticated) {
    await auth.fetchMe()
    alerts.fetchCounts()
    alertTimer = setInterval(() => alerts.fetchCounts(), 60_000)
  }
})

onUnmounted(() => {
  clearInterval(timer)
  clearInterval(alertTimer)
})

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>
