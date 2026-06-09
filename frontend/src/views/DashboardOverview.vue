<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-500">Vue d'ensemble</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">
          {{ selectedPointOfSaleLabel }}
        </p>
        <p class="text-sm text-slate-500">
          {{ filteredSalesCount.toLocaleString('fr-FR') }} vente{{ filteredSalesCount > 1 ? 's' : '' }} analysée{{ filteredSalesCount > 1 ? 's' : '' }}.
        </p>
      </div>
      <div v-if="isAdmin && pointOfSales.length" class="flex flex-col gap-2 text-sm text-slate-500">
        <label class="font-semibold text-slate-600">Point de vente</label>
        <select
          v-model="selectedPointOfSale"
          class="min-w-[220px] rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-200"
        >
          <option value="">Tous les points de vente</option>
          <option
            v-for="pos in availablePointOfSales"
            :key="getPointOfSaleId(pos) ?? pos?.id ?? pos?.name"
            :value="String(getPointOfSaleId(pos) ?? '')"
          >
            {{ formatPointOfSaleName(pos) }}
          </option>
        </select>
      </div>
    </div>

    <section v-if="canViewStats" class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="card in statCards"
        :key="card.id"
        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
      >
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-3">
            <span :class="['flex h-12 w-12 items-center justify-center rounded-xl', card.iconBg]">
              <FontAwesomeIcon :icon="card.icon" class="text-xl" />
            </span>
            <div>
              <p class="text-sm font-medium text-slate-500">{{ card.title }}</p>
              <p class="text-3xl font-semibold text-slate-900">{{ card.value }}</p>
            </div>
          </div>
          <span
            :class="[
              'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
              card.positive ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600',
            ]"
          >
            <FontAwesomeIcon :icon="card.positive ? faArrowTrendUp : faArrowTrendDown" class="mr-1" />
            {{ card.change }}
          </span>
        </div>
        <p class="mt-4 text-sm text-slate-500">{{ card.description }}</p>
      </article>
    </section>

    <section v-if="canViewStats" class="flex flex-col gap-6 lg:flex-row">
      <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:w-96 lg:flex-none">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500">Objectif mensuel</p>
            <p class="mt-1 text-base font-semibold text-slate-900">Objectif que vous vous êtes fixé chaque mois</p>
          </div>
          <button type="button" class="text-slate-300 transition hover:text-slate-500">
            <FontAwesomeIcon :icon="faEllipsis" />
          </button>
        </div>

        <div class="mt-6 flex flex-col items-center">
          <div class="relative h-40 w-40">
            <svg viewBox="0 0 120 120" class="h-full w-full">
              <circle
                cx="60"
                cy="60"
                r="52"
                class="text-slate-200"
                stroke="currentColor"
                stroke-width="12"
                fill="none"
              />
              <circle
                cx="60"
                cy="60"
                r="52"
                class="text-indigo-500"
                stroke="url(#targetGradient)"
                stroke-width="12"
                stroke-linecap="round"
                fill="none"
                :stroke-dasharray="gaugeStroke"
                :stroke-dashoffset="gaugeOffset"
                transform="rotate(-90 60 60)"
              />
              <defs>
                <linearGradient id="targetGradient" x1="0%" x2="0%" y1="0%" y2="100%">
                  <stop offset="0%" stop-color="#6366F1" />
                  <stop offset="100%" stop-color="#6366F1" stop-opacity="0.6" />
                </linearGradient>
              </defs>
              <circle cx="60" cy="60" r="41" fill="white" />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <p class="text-3xl font-semibold text-slate-900">{{ monthlyTargetProgressFormatted }}</p>
              <span
                class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="targetDeltaPositive ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
              >
                <FontAwesomeIcon :icon="targetDeltaIcon" />
                {{ targetDeltaDisplay }}
              </span>
            </div>
          </div>
          <p class="mt-4 text-center text-sm text-slate-500 max-w-xs">
            {{ monthlyTargetSummary }}
          </p>
        </div>

        <div class="mt-6 grid gap-3 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600 sm:grid-cols-3">
          <div v-for="item in monthlyTargetStats" :key="item.label" class="text-center sm:text-left">
            <p class="text-xs uppercase tracking-wide text-slate-400">{{ item.label }}</p>
            <p class="mt-1 text-base font-semibold text-slate-900">{{ item.value }}</p>
            <span
              :class="[
                'mt-1 inline-flex items-center gap-1 text-xs font-medium',
                item.positive ? 'text-emerald-600' : 'text-rose-600',
              ]"
            >
              <FontAwesomeIcon :icon="item.positive ? faArrowTrendUp : faArrowTrendDown" />
              {{ item.change }}
            </span>
          </div>
        </div>
      </article>

      <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm flex-1">
        <header class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-base font-semibold text-slate-900">Heatmap horaires</p>
            <p class="text-sm text-slate-500">
              Intensité des ventes par créneau pour les 7 derniers jours
            </p>
          </div>
        </header>

        <div class="mt-6 overflow-x-auto">
          <div v-if="heatmapData.hasData" class="inline-grid min-w-full gap-2">
            <div class="grid grid-cols-[110px_repeat(auto-fit,minmax(90px,1fr))] gap-2">
              <div></div>
              <div
                v-for="slot in heatmapData.slots"
                :key="slot.key"
                class="text-center text-xs font-semibold uppercase tracking-wide text-slate-400"
              >
                {{ slot.label }}
              </div>
              <template v-for="(day, dayIndex) in heatmapData.days" :key="`heatmap-row-${day.key}`">
                <div class="flex items-center text-sm font-semibold text-slate-600">
                  {{ day.labelFull }}
                </div>
                <div
                  v-for="(cell, slotIndex) in heatmapData.cells[dayIndex]"
                  :key="`cell-${day.key}-${slotIndex}`"
                  class="flex flex-col items-center justify-center rounded-xl border border-transparent px-3 py-2 text-xs font-semibold transition"
                  :style="heatmapCellStyle(cell.amount)"
                >
                  <span>{{ formatCurrencyShort(cell.amount) }}</span>
                  <span class="mt-1 text-[11px] font-medium opacity-70">
                    {{ cell.count }} vente{{ cell.count > 1 ? 's' : '' }}
                  </span>
                </div>
              </template>
            </div>
          </div>
          <div v-else class="flex h-40 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 text-center text-sm text-slate-500">
            Pas encore de ventes pour calculer le heatmap sur les 7 derniers jours.
          </div>
        </div>
      </article>
    </section>

    <section v-if="canViewStats" class="grid gap-6 grid-cols-1">
      <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <header class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-base font-semibold text-slate-900">Tendance quotidienne</p>
            <p class="text-sm text-slate-500">
              Derniers 7 jours — {{ dailyTrendSummary }}
            </p>
          </div>
          <span
            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
            :class="dailyTrendChange >= 0 ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'"
          >
            <FontAwesomeIcon :icon="dailyTrendChange >= 0 ? faArrowTrendUp : faArrowTrendDown" class="mr-1" />
            {{ formatChange(dailyTrendChange) }}
          </span>
        </header>

        <div class="mt-6 h-40">
          <div v-if="dailyTrendChart.line" class="h-full w-full">
            <svg viewBox="0 0 100 48" class="h-full w-full">
              <defs>
                <linearGradient id="dailyTrendGradient" x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%" stop-color="#6366F1" stop-opacity="0.25" />
                  <stop offset="100%" stop-color="#6366F1" stop-opacity="0" />
                </linearGradient>
              </defs>
              <path :d="dailyTrendChart.area" fill="url(#dailyTrendGradient)" />
              <path
                :d="dailyTrendChart.line"
                fill="none"
                stroke="#4F46E5"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
              <g v-for="(point, index) in dailyTrendBuckets" :key="`dot-${index}`">
                <circle
                  :cx="dailyTrendChart.points[index].x"
                  :cy="dailyTrendChart.points[index].y"
                  r="1.6"
                  fill="#4F46E5"
                />
              </g>
            </svg>
          </div>
          <div v-else class="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-500">
            Aucune donnée de vente pour les 7 derniers jours.
          </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
          <div
            v-for="bucket in dailyTrendBuckets"
            :key="bucket.key"
            class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600"
          >
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
              {{ bucket.labelFull }}
            </p>
            <p class="mt-1 text-lg font-semibold text-slate-900">
              {{ formatCurrency(bucket.value) }}
            </p>
            <p class="mt-0.5 text-xs text-slate-500">
              {{ bucket.count }} ticket{{ bucket.count > 1 ? 's' : '' }}
            </p>
          </div>
        </div>
      </article>
    </section>

    <section v-if="canViewStats" class="grid gap-6 xl:grid-cols-3">
      <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2">
        <header class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-base font-semibold text-slate-900">Ventes mensuelles</p>
            <p class="text-sm text-slate-500">Suivez le chiffre généré mois par mois</p>
          </div>
          <button type="button" class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600">
            Télécharger le rapport
          </button>
        </header>
        <div class="mt-6 h-56">
          <div v-if="monthlySalesHasData" class="flex h-full items-end gap-3">
            <div
              v-for="month in monthlySales"
              :key="month.label"
              class="flex flex-1 flex-col items-center gap-3"
            >
              <div class="flex h-full w-full items-end justify-center rounded-t-full bg-indigo-100">
                <div
                  class="relative w-10 rounded-t-full bg-indigo-500 shadow-inner"
                  :style="{ height: getMonthlySalesHeight(month.value) }"
                >
                  <span
                    class="absolute -top-7 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-600 shadow"
                  >
                    {{ formatCurrency(month.value) }}
                  </span>
                </div>
              </div>
              <span class="text-xs font-medium text-slate-500">{{ month.label }}</span>
            </div>
          </div>
          <div v-else class="flex h-full flex-col items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 text-center text-sm text-slate-500">
            <FontAwesomeIcon :icon="faArrowTrendUp" class="text-indigo-300" />
            <p>Aucune vente enregistrée sur les 12 derniers mois.</p>
            <p class="text-xs text-slate-400">Les ventes apparaîtront automatiquement après les premières transactions.</p>
          </div>
        </div>
      </article>

      <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <header class="flex items-start justify-between">
          <div>
            <p class="text-base font-semibold text-slate-900">Top produits</p>
            <p class="text-sm text-slate-500">Classement des articles les plus vendus</p>
          </div>
          <button type="button" class="text-slate-300 transition hover:text-slate-500" @click="activeProductView = activeProductView === 'quantité' ? 'montant' : 'quantité'">
            <FontAwesomeIcon :icon="faEllipsis" />
          </button>
        </header>

        <div class="mt-4 flex items-center justify-between rounded-xl bg-slate-100 p-1 text-xs font-semibold text-slate-600">
          <button
            v-for="view in productViewOptions"
            :key="view"
            type="button"
            class="flex-1 rounded-lg px-3 py-1 transition"
            :class="view === activeProductView ? 'bg-white text-slate-900 shadow-sm' : 'hover:text-slate-800'"
            @click="activeProductView = view"
          >
            {{ view === 'quantité' ? 'Par quantité' : 'Par montant' }}
          </button>
        </div>

        <ul class="mt-6 space-y-4">
          <li
            v-for="product in topProducts"
            :key="product.label"
            class="flex items-center justify-between"
          >
            <div class="flex items-center gap-3">
              <span :class="['flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold', product.color]">
                {{ product.initials }}
              </span>
              <div>
                <p class="text-sm font-semibold text-slate-700">{{ product.label }}</p>
                <p class="text-xs text-slate-400">{{ product.subtitle }}</p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-slate-900">{{ product.primary }}</p>
              <span class="text-xs font-medium text-slate-500">{{ product.secondary }}</span>
            </div>
          </li>
        </ul>
      </article>
    </section>

    <section v-if="canViewStats" class="grid gap-6 xl:grid-cols-3">
      <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-3">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-base font-semibold text-slate-900">Statistiques</p>
            <p class="text-sm text-slate-500">Objectif défini pour chaque période</p>
          </div>
          <div class="flex items-center gap-2 rounded-full bg-slate-100 p-1">
            <button
              v-for="tab in statisticsTabs"
              :key="tab"
              type="button"
              class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
              :class="tab === activeStatisticsTab ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
              @click="activeStatisticsTab = tab"
            >
              {{ tab }}
            </button>
          </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl bg-gradient-to-b from-indigo-50 to-white p-6">
          <svg viewBox="0 0 100 100" class="h-56 w-full">
            <defs>
              <linearGradient id="statsGradient" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="#6366F1" stop-opacity="0.25" />
                <stop offset="100%" stop-color="#6366F1" stop-opacity="0" />
              </linearGradient>
            </defs>
            <path
              v-if="statisticsChart.area"
              :d="statisticsChart.area"
              fill="url(#statsGradient)"
            />
            <path
              v-if="statisticsChart.line"
              :d="statisticsChart.line"
              fill="none"
              stroke="#6366F1"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <div class="mt-4 grid grid-cols-3 gap-4 text-xs text-slate-500 sm:text-sm">
            <div v-for="point in statisticsData" :key="point.label" class="text-center">
              <p class="font-medium text-slate-500">{{ point.label }}</p>
              <p class="mt-1 text-sm font-semibold text-slate-900 sm:text-base">{{ point.value }}</p>
            </div>
          </div>
        </div>
      </article>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import apiClient from '@/services/apiClient'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faArrowTrendDown,
  faArrowTrendUp,
  faChartLine,
  faCashRegister,
  faEllipsis,
  faReceipt,
} from '@fortawesome/free-solid-svg-icons'
import { API_BASE_URL } from '@/utils/api'
import { storage } from '@/utils/storage'
import { useAuth } from '@/composables/useAuth'
import { useCategories } from '@/composables/useCategories'

defineOptions({ name: 'DashboardOverview' })

const monthLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc']
const colorPalette = ['bg-indigo-100 text-indigo-600', 'bg-sky-100 text-sky-600', 'bg-emerald-100 text-emerald-600', 'bg-amber-100 text-amber-600']

const salesData = ref([])
const isLoading = ref(false)
const productViewOptions = ['quantité', 'montant']
const activeProductView = ref('quantité')
const pointOfSales = ref([])
const selectedPointOfSale = ref('')
const { isAdmin, hasRole, user: currentUser, loadUserData } = useAuth()
const canViewStats = computed(() => isAdmin.value || hasRole('gérant'))

const authHeaders = () => {
  const auth = storage.getAuth()
  if (!auth?.token) throw new Error('Token manquant. Veuillez vous reconnecter.')
  return { Authorization: `Bearer ${auth.token}` }
}

const extractArray = (payload) => {
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.items)) return payload.items
  if (Array.isArray(payload?.results)) return payload.results
  return []
}

const formatCurrency = (value) => {
  const amount = Number.isFinite(value) ? value : 0
  return `${Math.round(amount).toLocaleString('fr-FR')} Ar`
}

const formatCurrencyShort = (value) => {
  const amount = Number.isFinite(value) ? value : 0
  if (!amount) return '—'
  if (amount >= 1_000_000) return `${(amount / 1_000_000).toFixed(1)}M Ar`
  if (amount >= 100_000) return `${(amount / 1_000).toFixed(0)}k Ar`
  if (amount >= 10_000) return `${(amount / 1_000).toFixed(1)}k Ar`
  return `${Math.round(amount).toLocaleString('fr-FR')} Ar`
}

const getPointOfSaleId = (point) => {
  if (!point || typeof point !== 'object') return null
  return (
    point.id ??
    point.point_of_sale_id ??
    point.pointOfSaleId ??
    null
  )
}

const formatPointOfSaleName = (point) => {
  if (!point || typeof point !== 'object') return 'Point de vente'
  return point.name || point.label || `Point de vente #${getPointOfSaleId(point) ?? '—'}`
}

const getSalePointOfSaleId = (sale) => {
  if (!sale || typeof sale !== 'object') return null
  const register = sale.cash_register ?? sale.register ?? {}
  const point = register.point_of_sale ?? sale.point_of_sale ?? sale.pointOfSale ?? {}
  return (
    sale.point_of_sale_id ??
    sale.pointOfSaleId ??
    register.point_of_sale_id ??
    register.pointOfSaleId ??
    point.id ??
    null
  )
}

const getSaleUserId = (sale) => {
  if (!sale || typeof sale !== 'object') return null
  return sale.user_id ?? sale.userId ?? sale.user?.id ?? null
}

const getMonthKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
const getDayKey = (date) => date.toISOString().slice(0, 10)

const percentageChange = (current, previous) => {
  const currentValue = Number.isFinite(current) ? current : 0
  const previousValue = Number.isFinite(previous) ? previous : 0
  if (!previousValue) {
    return currentValue > 0 ? 100 : 0
  }
  return ((currentValue - previousValue) / Math.abs(previousValue)) * 100
}

const formatChange = (value) => {
  const numeric = Number.isFinite(value) ? value : 0
  if (!numeric) return '0%'
  const rounded = Math.abs(numeric) >= 10 ? numeric.toFixed(0) : numeric.toFixed(1)
  return `${numeric >= 0 ? '+' : ''}${rounded}%`
}

const getSaleAmount = (sale) => {
  const amount = Number(sale?.total_amount ?? sale?.total ?? sale?.amount ?? 0)
  return Number.isFinite(amount) ? amount : 0
}

const filteredSalesData = computed(() => {
  // Admins or Managers can view all sales for their POS/Organization
  if (isAdmin.value || hasRole('gérant')) {
    if (selectedPointOfSale.value) {
      const target = selectedPointOfSale.value
      return salesData.value.filter((sale) => String(getSalePointOfSaleId(sale) ?? '') === target)
    }
    return salesData.value
  }

  // Regular cashiers are restricted to their own sales
  const userId = currentUser.value?.id
  if (!userId) return []
  return salesData.value.filter((sale) => String(getSaleUserId(sale) ?? '') === String(userId))
})

const availablePointOfSales = computed(() =>
  pointOfSales.value.filter((point) => getPointOfSaleId(point) !== null)
)

const filteredSalesCount = computed(() => filteredSalesData.value.length)

const selectedPointOfSaleLabel = computed(() => {
  if (!isAdmin.value) {
    const name = currentUser.value?.name
    return name ? `Ventes de ${name}` : 'Mes ventes'
  }
  if (!selectedPointOfSale.value) return 'Tous les points de vente'
  const match = pointOfSales.value.find((point) => String(getPointOfSaleId(point) ?? '') === selectedPointOfSale.value)
  return match ? formatPointOfSaleName(match) : 'Tous les points de vente'
})

const selectedPointOfSaleDescription = computed(() => {
  if (!isAdmin.value) {
    const name = currentUser.value?.name
    return name ? `les ventes de ${name}` : 'vos ventes'
  }
  if (!selectedPointOfSale.value) return 'tous les points de vente'
  const match = pointOfSales.value.find((point) => String(getPointOfSaleId(point) ?? '') === selectedPointOfSale.value)
  return match ? formatPointOfSaleName(match) : 'tous les points de vente'
})

watch(pointOfSales, () => {
  if (!selectedPointOfSale.value) return
  const exists = pointOfSales.value.some((point) => String(getPointOfSaleId(point) ?? '') === selectedPointOfSale.value)
  if (!exists) selectedPointOfSale.value = ''
})

const dailyTrendBuckets = computed(() => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const days = []
  for (let offset = 6; offset >= 0; offset--) {
    const date = new Date(today)
    date.setDate(today.getDate() - offset)
    const key = getDayKey(date)
    days.push({
      key,
      label: date.toLocaleDateString('fr-FR', { weekday: 'short' }),
      labelFull: date.toLocaleDateString('fr-FR', { weekday: 'long', day: '2-digit', month: 'short' }),
      value: 0,
      count: 0,
    })
  }
  const map = Object.fromEntries(days.map((day) => [day.key, day]))

  filteredSalesData.value.forEach((sale) => {
    const createdAt = new Date(sale?.created_at ?? sale?.createdAt ?? sale?.date)
    if (Number.isNaN(createdAt.getTime())) return
    const key = getDayKey(createdAt)
    const bucket = map[key]
    if (!bucket) return
    bucket.value += getSaleAmount(sale)
    bucket.count += 1
  })

  return days
})

const dailyTrendTotal = computed(() => dailyTrendBuckets.value.reduce((sum, day) => sum + day.value, 0))

const previousWeekAmount = computed(() => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const end = new Date(today)
  end.setDate(end.getDate() - 7)
  const start = new Date(end)
  start.setDate(end.getDate() - 7)
  return filteredSalesData.value.reduce((sum, sale) => {
    const createdAt = new Date(sale?.created_at ?? sale?.createdAt ?? sale?.date)
    if (Number.isNaN(createdAt.getTime())) return sum
    if (createdAt >= start && createdAt < end) {
      return sum + getSaleAmount(sale)
    }
    return sum
  }, 0)
})

const dailyTrendChange = computed(() => percentageChange(dailyTrendTotal.value, previousWeekAmount.value))

const dailyTrendSummary = computed(() => {
  if (!filteredSalesData.value.length) return 'en attente de premières ventes'
  const latest = dailyTrendBuckets.value[dailyTrendBuckets.value.length - 1]
  if (!latest || latest.count === 0) return 'aucune vente aujourd\'hui'
  return `${latest.count} vente${latest.count > 1 ? 's' : ''} aujourd'hui`
})

const dailyTrendChart = computed(() => {
  const dataset = dailyTrendBuckets.value
  if (!dataset.length || dataset.every((point) => point.value === 0)) {
    return { line: '', area: '', points: [] }
  }

  const values = dataset.map((point) => point.value)
  const max = Math.max(...values)
  const min = Math.min(...values)
  const range = max - min || 1
  const padding = 6
  const height = 48 - padding * 2
  const stepX = dataset.length > 1 ? 100 / (dataset.length - 1) : 0

  const yFor = (value) => {
    const normalized = (value - min) / range
    return (48 - padding) - normalized * height
  }

  const points = dataset.map((point, index) => ({
    x: (index * stepX).toFixed(2),
    y: yFor(point.value).toFixed(2),
  }))

  let line = `M${points[0].x} ${points[0].y}`
  points.slice(1).forEach((point) => {
    line += ` L${point.x} ${point.y}`
  })

  const baseline = (48 - padding).toFixed(2)
  const area = `${line} L${points[points.length - 1].x} ${baseline} L${points[0].x} ${baseline} Z`

  return { line, area, points }
})

const heatmapSlots = [
  { key: '0', label: '00h', start: 0, end: 6 },
  { key: '6', label: '06h', start: 6, end: 9 },
  { key: '9', label: '09h', start: 9, end: 12 },
  { key: '12', label: '12h', start: 12, end: 15 },
  { key: '15', label: '15h', start: 15, end: 18 },
  { key: '18', label: '18h', start: 18, end: 21 },
  { key: '21', label: '21h+', start: 21, end: 24 },
]

const heatmapData = computed(() => {
  const days = dailyTrendBuckets.value
  const mapDayIndex = Object.fromEntries(days.map((day, index) => [day.key, index]))
  const cells = days.map(() => heatmapSlots.map(() => ({ amount: 0, count: 0 })))

  filteredSalesData.value.forEach((sale) => {
    const createdAt = new Date(sale?.created_at ?? sale?.createdAt ?? sale?.date)
    if (Number.isNaN(createdAt.getTime())) return
    const dayKey = getDayKey(createdAt)
    const dayIndex = mapDayIndex[dayKey]
    if (dayIndex === undefined) return
    const hour = createdAt.getHours()
    const slotIndex = heatmapSlots.findIndex((slot) => hour >= slot.start && hour < slot.end)
    if (slotIndex === -1) return
    const amount = getSaleAmount(sale)
    cells[dayIndex][slotIndex].amount += amount
    cells[dayIndex][slotIndex].count += 1
  })

  const flatAmounts = cells.flat().map((cell) => cell.amount)
  const maxAmount = Math.max(...flatAmounts, 0)
  const hasData = maxAmount > 0

  return { days, slots: heatmapSlots, cells, maxAmount, hasData }
})

const heatmapCellStyle = (amount) => {
  const max = heatmapData.value.maxAmount
  if (!max) {
    return {
      backgroundColor: 'rgba(148, 163, 184, 0.12)',
      color: '#1e293b',
    }
  }
  const intensity = Math.min(Math.max(amount / max, 0), 1)
  const alpha = 0.18 + intensity * 0.65
  return {
    backgroundColor: `rgba(99, 102, 241, ${alpha.toFixed(3)})`,
    color: intensity > 0.45 ? '#ffffff' : '#1e293b',
  }
}

const buildMonthlyBuckets = () => {
  const now = new Date()
  now.setHours(0, 0, 0, 0)
  const buckets = []
  for (let offset = 11; offset >= 0; offset--) {
    const monthDate = new Date(now.getFullYear(), now.getMonth() - offset, 1)
    const key = getMonthKey(monthDate)
    buckets.push({
      key,
      label: monthLabels[monthDate.getMonth()],
      value: 0,
      count: 0,
    })
  }
  return buckets
}

const monthlyBuckets = computed(() => {
  const buckets = buildMonthlyBuckets()
  const map = Object.fromEntries(buckets.map((bucket) => [bucket.key, bucket]))

  filteredSalesData.value.forEach((sale) => {
    const createdAt = new Date(sale?.created_at ?? sale?.createdAt ?? sale?.date)
    if (Number.isNaN(createdAt.getTime())) return
    const key = getMonthKey(createdAt)
    const bucket = map[key]
    if (!bucket) return
    bucket.value += getSaleAmount(sale)
    bucket.count += 1
  })

  return buckets
})

const monthlySales = computed(() => monthlyBuckets.value.map((bucket) => ({
  label: bucket.label,
  value: Math.round(bucket.value),
})))

const monthlySalesMax = computed(() => {
  if (!monthlySales.value.length) return 0
  return Math.max(...monthlySales.value.map((entry) => entry.value))
})

const getMonthlySalesHeight = (value) => {
  if (!monthlySalesMax.value) return '4px'
  const ratio = Math.min(Math.max(value / monthlySalesMax.value, 0), 1)
  const percent = Math.round(ratio * 100)
  return `calc(${percent}% + 4px)`
}

const monthlySalesHasData = computed(() => monthlySales.value.some((entry) => entry.value > 0))

const currentMonthBucket = computed(() => monthlyBuckets.value[monthlyBuckets.value.length - 1] ?? { value: 0, count: 0 })
const previousMonthBucket = computed(() => monthlyBuckets.value[monthlyBuckets.value.length - 2] ?? { value: 0, count: 0 })

const totalRevenue = computed(() => filteredSalesData.value.reduce((sum, sale) => sum + getSaleAmount(sale), 0))

const totalTickets = computed(() => filteredSalesData.value.length)

const averageTicket = computed(() => {
  if (!totalTickets.value) return 0
  return totalRevenue.value / totalTickets.value
})

const revenueGrowth = computed(() => percentageChange(currentMonthBucket.value.value, previousMonthBucket.value.value))
const ticketGrowth = computed(() => percentageChange(currentMonthBucket.value.count, previousMonthBucket.value.count))

const previousAverageTicket = computed(() => {
  if (!previousMonthBucket.value.count) return averageTicket.value
  return previousMonthBucket.value.value / previousMonthBucket.value.count
})

const averageTicketGrowth = computed(() => percentageChange(averageTicket.value, previousAverageTicket.value))

const statCards = computed(() => [
  {
    id: 'revenue',
    title: 'Chiffre d\'affaires',
    value: formatCurrency(totalRevenue.value),
    change: formatChange(revenueGrowth.value),
    positive: revenueGrowth.value >= 0,
    description: 'Variation par rapport au mois précédent.',
    icon: faCashRegister,
    iconBg: 'bg-indigo-100 text-indigo-600',
  },
  {
    id: 'orders',
    title: 'Ventes',
    value: totalTickets.value.toLocaleString('fr-FR'),
    change: formatChange(ticketGrowth.value),
    positive: ticketGrowth.value >= 0,
    description: 'Nombre de tickets enregistrés sur les 12 derniers mois.',
    icon: faReceipt,
    iconBg: 'bg-blue-100 text-blue-600',
  },
  {
    id: 'average-ticket',
    title: 'Ticket moyen',
    value: formatCurrency(averageTicket.value),
    change: formatChange(averageTicketGrowth.value),
    positive: averageTicketGrowth.value >= 0,
    description: 'Montant moyen par vente.',
    icon: faChartLine,
    iconBg: 'bg-emerald-100 text-emerald-600',
  },
])

const configuredMonthlyTarget = Number(import.meta.env?.VITE_MONTHLY_SALES_TARGET ?? 0)

const historicalAverage = computed(() => {
  const values = monthlyBuckets.value.map((bucket) => bucket.value).filter((value) => value > 0)
  if (!values.length) return 0
  const total = values.reduce((sum, value) => sum + value, 0)
  return total / values.length
})

const monthlyTarget = computed(() => {
  if (configuredMonthlyTarget > 0) return configuredMonthlyTarget
  if (historicalAverage.value > 0) return historicalAverage.value
  const previous = previousMonthBucket.value.value
  if (previous > 0) return previous
  const current = currentMonthBucket.value.value
  if (current > 0) return current
  return 0
})

const monthlyTargetProgress = computed(() => {
  if (!monthlyTarget.value) return 0
  return (currentMonthBucket.value.value / monthlyTarget.value) * 100
})
const monthlyTargetProgressFormatted = computed(() => `${monthlyTargetProgress.value.toFixed(2)}%`)

const gaugeRadius = 52
const gaugeCircumference = 2 * Math.PI * gaugeRadius
const gaugeStroke = `${gaugeCircumference} ${gaugeCircumference}`
const gaugeOffset = computed(() => {
  const value = Math.min(Math.max(monthlyTargetProgress.value, 0), 100)
  return ((100 - value) / 100) * gaugeCircumference
})

const monthlyTargetStats = computed(() => {
  const targetValue = monthlyTarget.value
  const hasTarget = targetValue > 0
  const currentValue = currentMonthBucket.value.value
  const delta = currentValue - (hasTarget ? targetValue : currentValue)
  const countDelta = currentMonthBucket.value.count - previousMonthBucket.value.count
  const countChange = countDelta === 0 && !previousMonthBucket.value.count && !currentMonthBucket.value.count
    ? '—'
    : `${countDelta >= 0 ? '+' : ''}${countDelta} vs mois préc.`
  return [
    {
      label: 'Objectif',
      value: hasTarget ? formatCurrency(targetValue) : '—',
      change: hasTarget ? formatCurrency(Math.abs(delta)) : '—',
      positive: !hasTarget || delta <= 0,
    },
    {
      label: 'Réalisé',
      value: formatCurrency(currentMonthBucket.value.value),
      change: formatChange(revenueGrowth.value),
      positive: revenueGrowth.value >= 0,
    },
    {
      label: 'Tickets',
      value: currentMonthBucket.value.count.toLocaleString('fr-FR'),
      change: countChange,
      positive: countDelta >= 0,
    },
  ]
})

const weeklyBuckets = computed(() => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const days = []
  for (let offset = 6; offset >= 0; offset--) {
    const date = new Date(today)
    date.setDate(today.getDate() - offset)
    const key = getDayKey(date)
    days.push({
      key,
      label: date.toLocaleDateString('fr-FR', { weekday: 'short' }),
      value: 0,
    })
  }
  const map = Object.fromEntries(days.map((day) => [day.key, day]))

  filteredSalesData.value.forEach((sale) => {
    const createdAt = new Date(sale?.created_at ?? sale?.createdAt ?? sale?.date)
    if (Number.isNaN(createdAt.getTime())) return
    createdAt.setHours(0, 0, 0, 0)
    const key = getDayKey(createdAt)
    const bucket = map[key]
    if (!bucket) return
    bucket.value += getSaleAmount(sale)
  })

  return days
})

const statisticsTabs = ['Hebdomadaire', 'Mensuel', 'Annuel']
const activeStatisticsTab = ref('Hebdomadaire')

const statisticsData = computed(() => {
  switch (activeStatisticsTab.value) {
    case 'Annuel':
      return monthlyBuckets.value.map((bucket) => ({ label: bucket.label, value: Math.round(bucket.value) }))
    case 'Mensuel': {
      const daysInMonth = new Date().getDate()
      const today = new Date()
      const start = new Date(today.getFullYear(), today.getMonth(), 1)
      const days = []
      for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(start.getFullYear(), start.getMonth(), day)
        if (date > today) break
        const key = getDayKey(date)
        days.push({ key, label: day.toString().padStart(2, '0'), value: 0 })
      }
      const map = Object.fromEntries(days.map((day) => [day.key, day]))
      filteredSalesData.value.forEach((sale) => {
        const createdAt = new Date(sale?.created_at ?? sale?.createdAt ?? sale?.date)
        if (Number.isNaN(createdAt.getTime())) return
        if (createdAt.getMonth() !== start.getMonth() || createdAt.getFullYear() !== start.getFullYear()) return
        const key = getDayKey(new Date(createdAt.getFullYear(), createdAt.getMonth(), createdAt.getDate()))
        const bucket = map[key]
        if (!bucket) return
        bucket.value += getSaleAmount(sale)
      })
      return days
    }
    case 'Hebdomadaire':
    default:
      return weeklyBuckets.value
  }
})

const statisticsChart = computed(() => {
  const dataset = statisticsData.value
  if (!dataset.length) {
    return { line: '', area: '' }
  }

  const values = dataset.map((point) => point.value)
  const max = Math.max(...values)
  const min = Math.min(...values)
  const range = max - min || 1
  const padding = 12
  const chartHeight = 100 - padding * 2
  const stepX = dataset.length > 1 ? 100 / (dataset.length - 1) : 0

  const yFor = (value) => {
    const normalized = (value - min) / range
    return (100 - padding) - normalized * chartHeight
  }

  let line = `M0 ${yFor(dataset[0].value).toFixed(2)}`

  dataset.slice(1).forEach((point, index) => {
    const x = ((index + 1) * stepX).toFixed(2)
    const y = yFor(point.value).toFixed(2)
    line += ` L${x} ${y}`
  })

  const baseline = (100 - padding).toFixed(2)
  const area = `${line} L100 ${baseline} L0 ${baseline} Z`

  return { line, area }
})

const getInitials = (label) => {
  if (!label) return '—'
  const parts = label.split(/[\s-_]+/).filter(Boolean)
  if (parts.length === 0) return label.slice(0, 2).toUpperCase()
  return parts.slice(0, 2).map((part) => part[0]).join('').toUpperCase()
}

const topProducts = computed(() => {
  const map = new Map()

  filteredSalesData.value.forEach((sale) => {
    const lines = Array.isArray(sale?.orderlines) ? sale.orderlines : []
    lines.forEach((line) => {
      const name = line?.product?.name ?? line?.product_name ?? 'Produit inconnu'
      if (!map.has(name)) {
        map.set(name, {
          name,
          totalAmount: 0,
          totalQuantity: 0,
          categories: new Set(),
        })
      }
      const entry = map.get(name)
      const quantity = Number(line?.quantity ?? 0)
      const amount = Number(line?.total ?? line?.total_amount ?? line?.amount ?? quantity * Number(line?.price ?? 0))
      entry.totalQuantity += Number.isFinite(quantity) ? quantity : 0
      entry.totalAmount += Number.isFinite(amount) ? amount : 0
      const categoryName = line?.product?.category?.name ?? line?.category?.name
      if (categoryName) entry.categories.add(categoryName)
    })
  })

  const dataset = Array.from(map.values())

  const sorted = dataset
    .sort((a, b) => {
      if (activeProductView.value === 'montant') {
        return b.totalAmount - a.totalAmount
      }
      return b.totalQuantity - a.totalQuantity
    })
    .slice(0, 5)

  return sorted.map((entry, index) => {
    const initials = getInitials(entry.name)
    const color = colorPalette[index % colorPalette.length]
    const subtitle = entry.categories.size ? Array.from(entry.categories).join(', ') : '—'
    return {
      label: entry.name,
      initials,
      color,
      subtitle,
      primary: activeProductView.value === 'montant' ? formatCurrency(entry.totalAmount) : `${entry.totalQuantity} vendu${entry.totalQuantity > 1 ? 's' : ''}`,
      secondary: activeProductView.value === 'montant' ? `${entry.totalQuantity} unité${entry.totalQuantity > 1 ? 's' : ''}` : formatCurrency(entry.totalAmount),
    }
  })
})

const loadSalesData = async () => {
  isLoading.value = true
  try {
    const now = new Date()
    const start = new Date(now.getFullYear(), now.getMonth() - 11, 1)
    const params = {
      start_date: start.toISOString().slice(0, 10),
      end_date: now.toISOString().slice(0, 10),
      per_page: 500,
    }
    const { data } = await apiClient.get('/sales', {
      params,
    })
    salesData.value = extractArray(data?.data ?? data)
    console.log('🔍 DEBUG: Dashboard sales loaded, total count:', salesData.value.length);
    if (salesData.value.length > 0) {
      console.log('🔍 DEBUG: Première vente récupérée:', salesData.value[0]);
    }
    console.log('🔍 DEBUG: isAdmin:', isAdmin.value, 'currentUser:', currentUser.value?.id);
  } catch (error) {
    console.error('Erreur chargement statistiques ventes:', error.response?.data || error.message)
    salesData.value = []
  } finally {
    isLoading.value = false
  }
}

const fetchPointOfSales = async () => {
  if (!isAdmin.value) {
    pointOfSales.value = []
    return
  }
  try {
    const { data } = await axios.get(`${API_BASE_URL}/point-of-sales`, {
      params: { per_page: 500 },
      headers: authHeaders(),
    })
    const points = extractArray(data?.data ?? data)
    const unique = []
    const seen = new Set()
    points.forEach((point) => {
      const id = getPointOfSaleId(point)
      if (id == null || seen.has(id)) return
      seen.add(id)
      unique.push(point)
    })
    pointOfSales.value = unique
  } catch (error) {
    console.error('Erreur chargement points de vente (dashboard):', error.response?.data || error.message)
    pointOfSales.value = []
  }
}

onMounted(async () => {
  // We no longer need to loadUserData or loadCategories here 
  // as they are handled by the DashboardLayout (Dashboard.vue)
  await Promise.allSettled([
    loadSalesData(), 
    fetchPointOfSales()
  ])
})

const targetDeltaValue = computed(() => currentMonthBucket.value.value - monthlyTarget.value)
const targetDeltaPositive = computed(() => targetDeltaValue.value >= 0)
const targetDeltaDisplay = computed(() => {
  if (!monthlyTarget.value) return formatCurrency(0)
  return formatCurrency(Math.abs(targetDeltaValue.value))
})

const monthlyTargetSummary = computed(() => {
  if (!filteredSalesData.value.length) {
    return 'Aucune vente enregistrée sur la période sélectionnée.'
  }
  const currentValue = currentMonthBucket.value.value
  if (currentValue === 0) {
    return 'Commencez à encaisser des ventes pour atteindre votre objectif mensuel.'
  }
  if (!monthlyTarget.value) {
    return `Vous avez généré ${formatCurrency(currentValue)} ce mois-ci sur ${selectedPointOfSaleDescription.value}. Définissez un objectif pour suivre vos progrès.`
  }
  if (targetDeltaValue.value >= 0) {
    return `Objectif dépassé de ${formatCurrency(targetDeltaValue.value)} ce mois-ci sur ${selectedPointOfSaleDescription.value}. Bravo !`
  }
  return `Encore ${formatCurrency(Math.abs(targetDeltaValue.value))} pour atteindre l'objectif sur ${selectedPointOfSaleDescription.value}. Vous y êtes presque.`
})

const targetDeltaIcon = computed(() => (targetDeltaPositive.value ? faArrowTrendUp : faArrowTrendDown))
</script>

<style scoped>
</style>
