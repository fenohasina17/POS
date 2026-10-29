<template>
  <div class="space-y-6">

    <!-- En-tête -->
    <div class="flex flex-wrap items-end gap-3">
      <div>
        <h1 class="text-xl font-black text-slate-900">Prix par point de vente</h1>
        <p class="text-xs text-slate-400">Prix appliqués par chaque caisse, remontés à chaque synchronisation du catalogue</p>
      </div>
      <div class="ml-auto flex flex-wrap items-end gap-2">
        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 shadow-sm">
          <input v-model="onlyOverrides" type="checkbox" class="h-4 w-4 accent-indigo-600" />
          Seulement les prix modifiés
        </label>
        <button @click="load" :disabled="loading" class="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50 transition">
          {{ loading ? 'Chargement…' : 'Actualiser' }}
        </button>
      </div>
    </div>

    <!-- Recherche -->
    <div class="relative max-w-xs">
      <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
      <input v-model="search" type="text" placeholder="Rechercher un produit…"
             class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-indigo-400 focus:outline-none" />
    </div>

    <p v-if="error" class="rounded-lg bg-red-50 px-4 py-2.5 text-sm text-red-600">{{ error }}</p>

    <!-- Tableau -->
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 text-left">
            <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Produit</th>
            <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Catégorie</th>
            <th class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-wide text-slate-400">Prix Central</th>
            <th v-for="r in restaurants" :key="r" class="whitespace-nowrap px-5 py-3 text-right text-[10px] font-black uppercase tracking-wide text-slate-400">{{ r }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="p in rows" :key="p.ref" class="hover:bg-slate-50 transition">
            <td class="px-5 py-3">
              <p class="font-semibold text-slate-900">{{ p.name }}</p>
              <p class="font-mono text-xs text-slate-400">{{ p.ref }}</p>
            </td>
            <td class="px-5 py-3">
              <span class="inline-block rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-600">
                {{ p.category ?? '—' }}
              </span>
              <span class="ml-1 text-[10px] font-black uppercase tracking-wide"
                    :class="p.price_editable ? 'text-emerald-600' : 'text-slate-400'">
                {{ p.price_editable ? 'Modifiable' : 'Fixe' }}
              </span>
            </td>
            <td class="px-5 py-3 text-right font-mono text-slate-700">{{ fmt(p.central_price) }}</td>
            <td v-for="r in restaurants" :key="r" class="whitespace-nowrap px-5 py-3 text-right font-mono">
              <template v-if="p.prices[r]">
                <span :class="p.prices[r].is_local_override ? 'font-bold text-amber-600' : 'text-slate-500'">
                  {{ fmt(p.prices[r].price) }}
                </span>
                <span v-if="p.prices[r].is_local_override" class="block text-[10px] font-sans font-semibold text-amber-600">
                  {{ diffLabel(p.prices[r].price, p.central_price) }}
                </span>
              </template>
              <span v-else class="text-slate-300">—</span>
            </td>
          </tr>
          <tr v-if="!loading && !rows.length">
            <td :colspan="3 + restaurants.length" class="px-5 py-10 text-center text-sm text-slate-400">
              {{ onlyOverrides ? 'Aucun prix modifié par un point de vente' : 'Aucun produit' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="!loading && !restaurants.length" class="text-xs text-slate-400">
      Aucune caisse n'a encore remonté ses prix : ils apparaissent après la prochaine synchronisation du catalogue (toutes les 5 minutes).
    </p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/utils/api'
import { fmt } from '@/utils/formatters'

const products      = ref([])
const restaurants   = ref([])
const loading       = ref(true)
const error         = ref('')
const search        = ref('')
const onlyOverrides = ref(false)

const rows = computed(() => {
  const q = search.value.trim().toLowerCase()
  return products.value.filter(p => {
    if (onlyOverrides.value && !Object.values(p.prices).some(x => x.is_local_override)) return false
    if (!q) return true
    return p.name.toLowerCase().includes(q) || p.ref.toLowerCase().includes(q) || (p.category ?? '').toLowerCase().includes(q)
  })
})

function diffLabel(price, central) {
  const d = Number(price) - Number(central)
  return d === 0 ? 'modifié' : `${d > 0 ? '+' : '−'}${fmt(Math.abs(d))}`
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/pos-prices')
    products.value    = res.data.products
    restaurants.value = res.data.restaurants
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Impossible de charger les prix.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
