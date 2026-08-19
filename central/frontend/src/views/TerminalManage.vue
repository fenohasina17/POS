<template>
  <div class="space-y-6 px-2 pt-4">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Administration</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">Gestion des terminaux</p>
        <p class="text-sm text-slate-500">Enregistrez, révoquez ou supprimez des terminaux.</p>
      </div>
      <button
        @click="showForm = !showForm"
        class="flex items-center gap-2 rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-indigo-700"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Enregistrer un terminal
      </button>
    </div>

    <!-- Formulaire nouveau terminal -->
    <div v-if="showForm" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <p class="mb-4 text-sm font-semibold text-slate-700">Nouveau terminal</p>
      <form @submit.prevent="createTerminal" class="flex flex-wrap gap-4">
        <input
          v-model="newTerminal.terminal_id"
          placeholder="ID terminal (ex: pos-paris-01)"
          required
          class="flex-1 min-w-48 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
        />
        <input
          v-model="newTerminal.restaurant_id"
          placeholder="ID restaurant (ex: restaurant-paris)"
          required
          class="flex-1 min-w-48 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100"
        />
        <button
          type="submit"
          :disabled="creating"
          class="rounded-2xl bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white transition hover:bg-indigo-700 disabled:opacity-50"
        >
          {{ creating ? 'Création…' : 'Créer' }}
        </button>
      </form>
    </div>

    <!-- Clé API générée -->
    <div v-if="generatedKey" class="flex items-start gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
      <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
      </span>
      <div class="flex-1">
        <p class="text-sm font-semibold text-emerald-800">Terminal créé — copiez la clé API maintenant</p>
        <p class="mb-2 text-xs text-emerald-600">Elle ne sera plus affichée après cette page.</p>
        <div class="flex items-center gap-3 rounded-xl bg-white border border-emerald-200 px-4 py-2">
          <code class="flex-1 break-all font-mono text-xs text-emerald-700">{{ generatedKey }}</code>
          <button @click="copyKey" class="text-[10px] font-bold text-emerald-600 hover:text-emerald-800 transition shrink-0">
            {{ copied ? '✓ Copié' : 'Copier' }}
          </button>
        </div>
        <p class="mt-2 text-[10px] text-slate-500">
          Configurez <code class="bg-slate-100 px-1 rounded">CENTRAL_API_KEY={{ generatedKey }}</code> dans le <code class="bg-slate-100 px-1 rounded">.env</code> de la caisse.
        </p>
      </div>
    </div>

    <!-- Tableau des terminaux -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
              <th class="px-5 py-3 text-left">Terminal</th>
              <th class="px-5 py-3 text-left">Restaurant</th>
              <th class="px-5 py-3 text-left">Statut</th>
              <th class="px-5 py-3 text-left">Dernier heartbeat</th>
              <th class="px-5 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="t in terminals"
              :key="t.terminal_id"
              class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors"
            >
              <td class="px-5 py-3 font-mono font-semibold text-slate-800">{{ t.terminal_id }}</td>
              <td class="px-5 py-3 text-slate-500">{{ t.restaurant_id }}</td>
              <td class="px-5 py-3">
                <span
                  :class="t.status === 'online' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600'"
                  class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-black uppercase"
                >
                  <span class="h-1.5 w-1.5 rounded-full" :class="t.status === 'online' ? 'bg-emerald-500' : 'bg-rose-400'"></span>
                  {{ t.status === 'online' ? 'En ligne' : 'Hors ligne' }}
                </span>
              </td>
              <td class="px-5 py-3 text-xs text-slate-400">{{ fmtDate(t.last_heartbeat_at) }}</td>
              <td class="px-5 py-3 text-right">
                <div class="flex items-center justify-end gap-3">
                  <button @click="rotate(t.terminal_id)" class="text-xs font-semibold text-amber-500 hover:text-amber-700 transition-colors">
                    Révoquer clé
                  </button>
                  <button @click="remove(t.terminal_id)" class="text-xs font-semibold text-rose-500 hover:text-rose-700 transition-colors">
                    Supprimer
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!terminals.length">
              <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-400">Aucun terminal enregistré.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal clé révoquée -->
    <div v-if="rotatedKey" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
      <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
        <p class="mb-3 text-sm font-bold text-amber-600">Nouvelle clé API générée</p>
        <code class="block break-all rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 font-mono text-xs text-amber-700 mb-3">{{ rotatedKey }}</code>
        <p class="mb-5 text-xs text-slate-500">
          Mettez à jour <code class="bg-slate-100 px-1 rounded">CENTRAL_API_KEY</code> dans le <code class="bg-slate-100 px-1 rounded">.env</code> de la caisse, puis redémarrez-la.
        </p>
        <button @click="rotatedKey = ''" class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 transition">
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
const newTerminal  = ref({ terminal_id: '', restaurant_id: '' })

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

const fmtDate = val => val ? new Date(val).toLocaleString('fr-FR') : '—'
</script>
