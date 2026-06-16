<template>
  <div class="p-4 md:p-8 space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">ADMINISTRATION</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-history" class="text-indigo-500" />
          Historique des Sessions
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Consultez et gérez toutes les sessions de caisse ouvertes et fermées.
        </p>
      </div>
      <button
        @click="fetchSessions"
        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600 shadow-sm"
        :disabled="loading"
      >
        <font-awesome-icon icon="fa-solid fa-rotate" :class="{ 'animate-spin': loading }" />
        Actualiser
      </button>
    </header>

    <!-- Filtres -->
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="flex flex-wrap items-center gap-6">
        <div class="flex items-center gap-2 text-sm font-bold text-slate-600">
          <font-awesome-icon icon="fa-solid fa-filter" class="text-indigo-500" />
          Filtrer par :
        </div>
        <div class="flex flex-wrap gap-4 items-center">
          <select v-model="filters.point_of_sale_id" @change="fetchSessions" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-bold text-slate-700 outline-none focus:border-indigo-500 transition-all">
            <option :value="null">Tous les sites</option>
            <option v-for="pos in pointsOfSale" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
          </select>
          
          <select v-model="filters.status" @change="fetchSessions" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-bold text-slate-700 outline-none focus:border-indigo-500 transition-all">
            <option value="">Tous les statuts</option>
            <option value="open">Ouvertes</option>
            <option value="closed">Fermées</option>
          </select>
        </div>
      </div>
    </section>

    <!-- Liste des Sessions -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-slate-400">
        <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-100 border-t-indigo-600"></div>
        <p class="mt-4 text-xs font-bold uppercase tracking-widest">Chargement des sessions...</p>
      </div>

      <div v-else-if="sessions.length === 0" class="py-20 text-center">
        <font-awesome-icon icon="fa-solid fa-history" class="text-4xl text-slate-200 mb-4" />
        <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Aucune session trouvée</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/50 border-b border-slate-100">
              <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">ID / Caisse</th>
              <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Utilisateur</th>
              <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Ouverture</th>
              <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Fermeture</th>
              <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400 text-right">CA Réel</th>
              <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Statut</th>
              <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <tr v-for="session in sessions" :key="session.id" class="group hover:bg-slate-50/50 transition-all">
              <td class="px-6 py-4">
                <p class="font-black text-slate-700 uppercase text-xs">#{{ session.id }}</p>
                <p class="text-[10px] font-bold text-slate-400 uppercase">{{ session.cash_register?.name || 'N/A' }}</p>
              </td>
              <td class="px-6 py-4 text-xs font-bold text-slate-600">{{ session.user?.name || 'Inconnu' }}</td>
              <td class="px-6 py-4 text-[10px] font-bold text-slate-500">{{ formatDate(session.opened_at) }}</td>
              <td class="px-6 py-4 text-[10px] font-bold text-slate-500">{{ formatDate(session.closed_at) || '-' }}</td>
              <td class="px-6 py-4 text-right">
                <p v-if="session.actual_cash_amount !== null" class="text-xs font-black text-indigo-600">{{ formatPrice(session.actual_cash_amount) }}</p>
                <p v-else class="text-[10px] font-bold text-slate-300 italic">Non clôturée</p>
              </td>
              <td class="px-6 py-4">
                <div class="flex justify-center">
                  <span 
                    v-if="!session.is_closed" 
                    class="rounded-full bg-emerald-50 px-3 py-1 text-[9px] font-black uppercase tracking-tighter text-emerald-600 border border-emerald-100 shadow-sm"
                  >
                    Ouverte
                  </span>
                  <span 
                    v-else 
                    class="rounded-full bg-slate-100 px-3 py-1 text-[9px] font-black uppercase tracking-tighter text-slate-400 border border-slate-200"
                  >
                    Fermée
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="flex justify-end gap-2">
                  <button 
                    v-if="session.is_closed"
                    @click="reopenSession(session)"
                    :disabled="processingId === session.id"
                    class="h-8 px-3 flex items-center gap-2 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all active:scale-95 text-[10px] font-black uppercase shadow-sm border border-indigo-100 disabled:opacity-50"
                  >
                    <font-awesome-icon v-if="processingId === session.id" icon="fa-solid fa-circle-notch" class="animate-spin" />
                    <font-awesome-icon v-else icon="fa-solid fa-unlock" />
                    Réouvrir
                  </button>
                  <router-link
                    v-if="session.is_closed"
                    :to="{ name: 'billetage-summary', params: { sessionId: session.id } }"
                    class="h-8 w-8 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-white hover:text-indigo-600 hover:border-indigo-100 hover:shadow-md transition-all active:scale-95 shadow-sm border border-slate-100"
                    title="Voir le récapitulatif"
                  >
                    <font-awesome-icon icon="fa-solid fa-file-invoice" class="text-xs" />
                  </router-link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import apiClient from '@/services/apiClient'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { 
  faHistory, faRotate, faFilter, faUnlock, faCircleNotch, faFileInvoice
} from '@fortawesome/free-solid-svg-icons'

library.add(faHistory, faRotate, faFilter, faUnlock, faCircleNotch, faFileInvoice)

const sessions = ref([])
const pointsOfSale = ref([])
const loading = ref(false)
const processingId = ref(null)

const filters = reactive({
  point_of_sale_id: null,
  status: ''
})

const formatDate = (date) => {
  if (!date) return null
  return new Date(date).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatPrice = (price) => {
  return new Intl.NumberFormat('fr-FR').format(price) + ' Ar'
}

const fetchPointsOfSale = async () => {
  try {
    const { data } = await apiClient.get('/point-of-sales')
    pointsOfSale.value = data?.data || data || []
  } catch (err) {
    console.error('Erreur chargement POS:', err)
  }
}

const fetchSessions = async () => {
  loading.value = true
  try {
    const params = {}
    if (filters.point_of_sale_id) params.point_of_sale_id = filters.point_of_sale_id
    if (filters.status) params.status = filters.status

    const { data } = await apiClient.get('/cash-register-sessions', { params })
    // Le backend retourne la liste directement ou sous data.data
    sessions.value = Array.isArray(data) ? data : data?.data || []
  } catch (err) {
    console.error('Erreur chargement sessions:', err)
    alert('Impossible de charger les sessions.')
  } finally {
    loading.value = false
  }
}

const reopenSession = async (session) => {
  if (!confirm(`Voulez-vous vraiment réouvrir la session #${session.id} ? Le montant déjà compté sera réinitialisé.`)) return

  processingId.value = session.id
  try {
    await apiClient.post(`/cash-register-sessions/${session.id}/reopen`)
    alert('Session réouverte avec succès.')
    await fetchSessions()
  } catch (err) {
    console.error('Erreur réouverture:', err)
    alert(err.response?.data?.message || 'Erreur lors de la réouverture.')
  } finally {
    processingId.value = null
  }
}

onMounted(() => {
  fetchPointsOfSale()
  fetchSessions()
})
</script>

<style scoped>
/* Styles spécifiques si nécessaire */
</style>
