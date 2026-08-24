<template>
  <div class="space-y-4 px-2 pt-4">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Réseau</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">Restaurants</p>
        <p class="text-sm text-slate-500">Vue d'ensemble par établissement.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <input type="date" v-model="dateFrom" @change="load"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300" />
        <input type="date" v-model="dateTo" @change="load"
          class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 outline-none focus:border-indigo-300" />
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="grid gap-4 lg:grid-cols-2">
      <div v-for="i in 2" :key="i" class="h-64 animate-pulse rounded-2xl bg-slate-100"></div>
    </div>

    <template v-else-if="data">

      <!-- KPIs réseau -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">CA total réseau</p>
          <p class="mt-1 text-2xl font-black text-indigo-600">{{ fmtShort(data.totals.ca) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Ventes</p>
          <p class="mt-1 text-2xl font-black text-slate-800">{{ data.totals.sales_count.toLocaleString('fr-FR') }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Restaurants</p>
          <p class="mt-1 text-2xl font-black text-slate-800">{{ data.totals.restaurants }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Terminaux</p>
          <p class="mt-1 text-2xl font-black text-slate-800">{{ data.totals.terminals }}</p>
        </div>
      </div>

      <!-- Cartes par restaurant -->
      <div class="grid gap-4 lg:grid-cols-2">
        <div
          v-for="r in data.restaurants"
          :key="r.restaurant_id"
          class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
        >
          <!-- Entête carte -->
          <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <div class="flex items-center gap-3">
              <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100">
                <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-900">{{ r.restaurant_id }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="flex items-center gap-1 text-[10px]">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 inline-block"></span>
                    <span class="text-emerald-600 font-semibold">{{ r.terminals.online }} en ligne</span>
                  </span>
                  <span v-if="r.terminals.offline > 0" class="flex items-center gap-1 text-[10px]">
                    <span class="h-1.5 w-1.5 rounded-full bg-slate-300 inline-block"></span>
                    <span class="text-slate-400">{{ r.terminals.offline }} hors ligne</span>
                  </span>
                </div>
              </div>
            </div>
            <div class="text-right">
              <p class="text-lg font-black text-indigo-600">{{ fmtShort(r.ca) }}</p>
              <p class="text-[10px] text-slate-400">{{ r.sales_count }} ventes</p>
            </div>
          </div>

          <!-- Corps carte -->
          <div class="grid grid-cols-2 divide-x divide-slate-100">

            <!-- Top vendeurs -->
            <div class="px-4 py-3">
              <p class="mb-2 text-[9px] font-black uppercase tracking-wider text-slate-400">Top vendeurs</p>
              <ul class="space-y-1.5">
                <li v-for="(s, i) in r.top_sellers" :key="s.seller_name" class="flex items-center gap-2">
                  <span class="text-xs">{{ ['🥇','🥈','🥉'][i] }}</span>
                  <span class="flex-1 truncate text-xs text-slate-700">{{ s.seller_name }}</span>
                  <span class="text-[10px] font-bold text-slate-500">{{ fmtShort(s.ca) }}</span>
                </li>
                <li v-if="!r.top_sellers.length" class="text-[10px] text-slate-300">Aucune donnée</li>
              </ul>
            </div>

            <!-- Top produits -->
            <div class="px-4 py-3">
              <p class="mb-2 text-[9px] font-black uppercase tracking-wider text-slate-400">Top produits</p>
              <ul class="space-y-1.5">
                <li v-for="(p, i) in r.top_products" :key="p.product_name" class="flex items-center gap-2">
                  <span class="text-xs">{{ ['🥇','🥈','🥉'][i] }}</span>
                  <span class="flex-1 truncate text-xs text-slate-700">{{ p.product_name }}</span>
                  <span class="text-[10px] font-bold text-slate-500">{{ fmtShort(p.ca) }}</span>
                </li>
                <li v-if="!r.top_products.length" class="text-[10px] text-slate-300">Aucune donnée</li>
              </ul>
            </div>
          </div>

          <!-- Barre tendance 7j -->
          <div class="border-t border-slate-100 px-4 py-3">
            <p class="mb-2 text-[9px] font-black uppercase tracking-wider text-slate-400">Tendance 7 jours</p>
            <div v-if="r.trend.length" class="flex items-end gap-1 h-8">
              <div
                v-for="day in r.trend"
                :key="day.day"
                class="flex-1 rounded-sm bg-indigo-400 transition-all hover:bg-indigo-500"
                :style="{ height: trendHeight(day.ca, r.trend) }"
                :title="`${day.day}: ${fmt(day.ca)}`"
              ></div>
            </div>
            <p v-else class="text-[10px] text-slate-300">Pas de données récentes.</p>
          </div>

          <!-- Lien filtrer -->
          <div class="border-t border-slate-100 px-4 py-2 flex gap-4">
            <button @click="goToSales(r.restaurant_id)"
              class="text-[10px] font-semibold text-indigo-500 hover:text-indigo-700 transition">
              Voir les ventes →
            </button>
            <button @click="goToSellers(r.restaurant_id)"
              class="text-[10px] font-semibold text-slate-400 hover:text-slate-600 transition">
              Voir les vendeurs →
            </button>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/utils/api'
import { fmt, fmtShort } from '@/utils/formatters'

const router  = useRouter()
const data    = ref(null)
const loading = ref(false)

const oneMonthAgo = new Date(Date.now() - 30 * 86400_000).toISOString().slice(0, 10)
const today       = new Date().toISOString().slice(0, 10)
const dateFrom    = ref(oneMonthAgo)
const dateTo      = ref(today)

const trendHeight = (ca, trend) => {
  const max = Math.max(...trend.map(d => d.ca), 1)
  return `${Math.max(8, (ca / max) * 100).toFixed(0)}%`
}

function goToSales(restaurantId) {
  router.push({ name: 'sales' })
}

function goToSellers(restaurantId) {
  router.push({ name: 'seller-report' })
}

async function load() {
  loading.value = true
  try {
    const res = await api.get('/restaurants', {
      params: {
        date_from: dateFrom.value,
        date_to:   dateTo.value,
      },
    })
    data.value = res.data
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
