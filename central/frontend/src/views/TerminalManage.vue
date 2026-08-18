<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Gestion des terminaux</h1>
      <button @click="showForm = !showForm"
        class="bg-emerald-600 hover:bg-emerald-500 text-white text-sm px-4 py-2 rounded-lg transition-colors">
        + Enregistrer un terminal
      </button>
    </div>

    <!-- Formulaire nouveau terminal -->
    <div v-if="showForm" class="bg-gray-800 rounded-xl p-6 mb-6">
      <h2 class="font-semibold mb-4 text-gray-300">Nouveau terminal</h2>
      <form @submit.prevent="createTerminal" class="flex flex-wrap gap-4">
        <input v-model="newTerminal.terminal_id" placeholder="ID terminal (ex: pos-paris-01)"
          required class="input-field flex-1 min-w-48" />
        <input v-model="newTerminal.restaurant_id" placeholder="ID restaurant (ex: restaurant-paris)"
          required class="input-field flex-1 min-w-48" />
        <button type="submit" :disabled="creating"
          class="bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-sm px-4 py-2 rounded-lg transition-colors">
          {{ creating ? 'Création...' : 'Créer' }}
        </button>
      </form>
    </div>

    <!-- Clé API générée -->
    <div v-if="generatedKey" class="bg-emerald-900/30 border border-emerald-700 rounded-xl p-5 mb-6">
      <p class="text-emerald-400 font-semibold mb-2">✓ Terminal créé — clé API (copiez-la maintenant, elle ne sera plus affichée)</p>
      <div class="flex items-center gap-3">
        <code class="bg-gray-900 rounded px-3 py-2 text-emerald-300 text-sm font-mono flex-1 break-all">{{ generatedKey }}</code>
        <button @click="copyKey" class="text-xs text-gray-400 hover:text-white shrink-0">
          {{ copied ? '✓ Copié' : 'Copier' }}
        </button>
      </div>
      <p class="text-xs text-gray-500 mt-2">
        Configurez <code>CENTRAL_API_KEY={{ generatedKey }}</code> dans le <code>.env</code> de la caisse.
      </p>
    </div>

    <!-- Liste des terminaux -->
    <div class="bg-gray-800 rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-gray-500 border-b border-gray-700 text-xs uppercase tracking-wider">
            <th class="text-left px-5 py-3">Terminal</th>
            <th class="text-left px-5 py-3">Restaurant</th>
            <th class="text-left px-5 py-3">Statut</th>
            <th class="text-left px-5 py-3">Dernier heartbeat</th>
            <th class="text-right px-5 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in terminals" :key="t.terminal_id"
            class="border-b border-gray-700/50 hover:bg-gray-750 transition-colors">
            <td class="px-5 py-3 font-mono text-gray-200">{{ t.terminal_id }}</td>
            <td class="px-5 py-3 text-gray-400">{{ t.restaurant_id }}</td>
            <td class="px-5 py-3">
              <span :class="t.status === 'online' ? 'text-emerald-400' : 'text-red-400'">
                {{ t.status === 'online' ? '● En ligne' : '○ Hors ligne' }}
              </span>
            </td>
            <td class="px-5 py-3 text-gray-500 text-xs">{{ formatDate(t.last_heartbeat_at) }}</td>
            <td class="px-5 py-3 text-right">
              <div class="flex items-center justify-end gap-3">
                <button @click="rotate(t.terminal_id)"
                  class="text-xs text-yellow-400 hover:text-yellow-300 transition-colors">
                  Révoquer clé
                </button>
                <button @click="remove(t.terminal_id)"
                  class="text-xs text-red-400 hover:text-red-300 transition-colors">
                  Supprimer
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!terminals.length">
            <td colspan="5" class="px-5 py-8 text-center text-gray-600">Aucun terminal enregistré.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal clé révoquée -->
    <div v-if="rotatedKey" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
      <div class="bg-gray-800 rounded-2xl p-6 max-w-lg w-full">
        <h3 class="font-semibold text-yellow-400 mb-3">Nouvelle clé API générée</h3>
        <code class="block bg-gray-900 rounded px-3 py-2 text-yellow-300 text-sm font-mono break-all mb-4">{{ rotatedKey }}</code>
        <p class="text-xs text-gray-500 mb-4">
          Mettez à jour <code>CENTRAL_API_KEY</code> dans le <code>.env</code> de la caisse, puis redémarrez-la.
        </p>
        <button @click="rotatedKey = ''" class="bg-gray-700 hover:bg-gray-600 text-white text-sm px-4 py-2 rounded-lg w-full">
          Fermer
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/utils/api'

const terminals    = ref([])
const showForm     = ref(false)
const creating     = ref(false)
const generatedKey = ref('')
const rotatedKey   = ref('')
const copied       = ref(false)

const newTerminal = ref({ terminal_id: '', restaurant_id: '' })

onMounted(fetchTerminals)

async function fetchTerminals() {
  const res = await api.get('/terminals')
  terminals.value = res.data
}

async function createTerminal() {
  creating.value = true
  try {
    const res = await api.post('/terminals', newTerminal.value)
    generatedKey.value = res.data.api_key
    terminals.value.push(res.data)
    newTerminal.value = { terminal_id: '', restaurant_id: '' }
    showForm.value = false
  } catch (e) {
    alert(e.response?.data?.message ?? 'Erreur lors de la création.')
  } finally {
    creating.value = false
  }
}

async function rotate(terminalId) {
  if (!confirm(`Révoquer et regénérer la clé API de ${terminalId} ?`)) return
  const res = await api.post(`/terminals/${terminalId}/rotate-key`)
  rotatedKey.value = res.data.api_key
}

async function remove(terminalId) {
  if (!confirm(`Supprimer définitivement le terminal ${terminalId} ?`)) return
  await api.delete(`/terminals/${terminalId}`)
  terminals.value = terminals.value.filter(t => t.terminal_id !== terminalId)
}

async function copyKey() {
  await navigator.clipboard.writeText(generatedKey.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleString('fr-FR')
}
</script>

<style scoped>
.input-field {
  @apply bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200
         focus:outline-none focus:border-emerald-500 transition-colors;
}
</style>
