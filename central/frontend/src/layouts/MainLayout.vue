<template>
  <div class="flex min-h-screen bg-slate-100 text-slate-900">
    <!-- Overlay mobile -->
    <div
      class="fixed inset-0 z-30 bg-slate-900/40 transition-opacity duration-200 lg:hidden"
      :class="sidebarOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'"
      @click="sidebarOpen = false"
    ></div>

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-40 border-r border-slate-200 bg-white shadow-sm transition-all duration-200 ease-in-out',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        'lg:translate-x-0',
        sidebarCollapsed ? 'lg:w-16' : 'lg:w-56',
      ]"
    >
      <div class="flex h-full flex-col">
        <!-- Logo -->
        <div class="flex items-center gap-2 px-4 pt-4 shrink-0" :class="sidebarCollapsed ? 'lg:px-2 lg:justify-center' : ''">
          <img src="../assets/logoigp.jpg" alt="IGP" class="h-9 w-auto rounded-lg object-cover" />
          <div v-if="!sidebarCollapsed" class="hidden lg:block">
            <p class="text-sm font-semibold text-slate-900">IGP Central</p>
            <p class="text-[10px] text-slate-400">Supervision siège</p>
          </div>
        </div>

        <!-- Navigation -->
        <nav class="mt-8 flex-1 px-3 space-y-5" :class="sidebarCollapsed ? 'lg:px-1' : ''">
          <div v-for="section in navSections" :key="section.title">
            <p v-if="!sidebarCollapsed" class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ section.title }}</p>
            <ul class="space-y-1">
              <li v-for="item in section.items" :key="item.route">
                <button
                  type="button"
                  :class="[
                    'flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium transition',
                    isActive(item) ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-slate-100',
                    sidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-0 lg:py-2' : '',
                  ]"
                  @click="navigate(item.route)"
                >
                  <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                    :class="isActive(item) ? 'bg-white text-indigo-600 shadow-sm' : 'bg-slate-100 text-slate-500'"
                  >
                    <component :is="item.icon" class="h-4 w-4" />
                  </span>
                  <span v-if="!sidebarCollapsed" class="flex-1 text-left text-xs">{{ item.label }}</span>
                </button>
              </li>
            </ul>
          </div>
        </nav>
      </div>
    </aside>

    <!-- Content -->
    <div :class="['flex min-h-screen flex-1 flex-col transition-all duration-200', sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-56']">
      <!-- Header -->
      <header
        :class="[
          'fixed top-0 right-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur',
          'left-0 transition-all duration-200',
          sidebarCollapsed ? 'lg:left-16' : 'lg:left-56',
        ]"
      >
        <div class="flex items-center gap-3 px-3 py-2 sm:px-4">
          <!-- Toggle sidebar -->
          <button
            @click="toggleSidebar"
            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <!-- Titre dynamique -->
          <div class="hidden sm:flex items-center gap-2 px-3 py-1 bg-indigo-50 border border-indigo-100 rounded-full">
            <span class="flex h-2 w-2 rounded-full bg-indigo-500 animate-pulse"></span>
            <span class="text-[10px] font-black text-indigo-700 uppercase tracking-tight">Supervision centrale</span>
          </div>

          <div class="flex flex-1 items-center justify-end gap-2">
            <!-- Alertes badge -->
            <button
              v-if="alerts.counts.critical > 0 || alerts.counts.warning > 0"
              @click="navigate('dashboard')"
              class="relative flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:border-indigo-200 hover:text-indigo-600 transition"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <span class="absolute right-1 top-1 flex h-2 w-2 rounded-full"
                :class="alerts.counts.critical > 0 ? 'bg-red-500' : 'bg-amber-400'">
              </span>
            </button>

            <!-- User menu -->
            <div ref="userMenuRef" class="relative">
              <button
                @click="userMenuOpen = !userMenuOpen"
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2 py-1 text-sm font-medium text-slate-600 shadow-sm transition hover:border-indigo-200"
              >
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-black">
                  {{ initials }}
                </span>
                <span class="hidden text-xs font-semibold text-slate-700 sm:inline">{{ auth.user?.name }}</span>
                <svg class="hidden h-3 w-3 text-slate-400 sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <div
                v-if="userMenuOpen"
                class="absolute right-0 top-full mt-2 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg z-50"
              >
                <div class="border-b border-slate-100 px-4 py-2">
                  <p class="text-xs font-medium text-slate-800">{{ auth.user?.name }}</p>
                  <p class="text-[10px] text-slate-500">{{ auth.user?.email }}</p>
                </div>
                <button
                  @click="logout"
                  class="w-full px-4 py-2 text-left text-xs text-slate-600 hover:bg-slate-50 hover:text-indigo-600"
                >
                  Déconnexion
                </button>
              </div>
            </div>
          </div>
        </div>
      </header>

      <!-- Main -->
      <main class="flex-1 pb-6 pt-14 sm:px-4 lg:px-5 lg:pt-[3.5rem]">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAlertsStore } from '@/stores/alerts'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()
const alerts = useAlertsStore()

const sidebarOpen      = ref(false)
const sidebarCollapsed = ref(false)
const userMenuOpen     = ref(false)
const userMenuRef      = ref(null)
const isDesktop        = ref(false)

const initials = computed(() => {
  const name = auth.user?.name ?? ''
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() || 'A'
})

// Icônes SVG inline comme composants fonctionnels
const IconGauge   = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>` }
const IconServer  = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>` }
const IconReceipt = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 000 4h6a2 2 0 000-4M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>` }
const IconSession = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>` }
const IconCog     = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>` }
const IconChart   = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>` }

const navSections = [
  {
    title: 'Menu',
    items: [
      { label: 'Vue d\'ensemble', route: 'dashboard',        icon: IconGauge   },
      { label: 'Terminaux',       route: 'terminals',         icon: IconServer  },
      { label: 'Ventes',          route: 'sales',             icon: IconReceipt },
      { label: 'Sessions',        route: 'sessions',          icon: IconSession },
      { label: 'Produits',        route: 'product-report',    icon: IconChart   },
    ],
  },
  {
    title: 'Outils',
    items: [
      { label: 'Gestion', route: 'terminal-manage', icon: IconCog },
    ],
  },
]
// Compatibilité avec isActive
const navItems = navSections.flatMap(s => s.items)

const isActive = (item) => route.name === item.route

function navigate(name) {
  sidebarOpen.value = false
  userMenuOpen.value = false
  router.push({ name })
}

function toggleSidebar() {
  if (!isDesktop.value) {
    sidebarOpen.value = !sidebarOpen.value
  } else {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }
}

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}

function handleDocumentClick(e) {
  if (userMenuRef.value && !userMenuRef.value.contains(e.target)) {
    userMenuOpen.value = false
  }
}

function handleResize() {
  isDesktop.value = window.innerWidth >= 1024
  if (isDesktop.value) {
    sidebarOpen.value = true
  } else {
    sidebarOpen.value = false
    sidebarCollapsed.value = false
  }
}

onMounted(() => {
  handleResize()
  window.addEventListener('resize', handleResize)
  document.addEventListener('click', handleDocumentClick)
  alerts.fetchCounts()
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
  document.removeEventListener('click', handleDocumentClick)
})
</script>

<style scoped>
aside::-webkit-scrollbar { width: 4px; }
aside::-webkit-scrollbar-thumb { border-radius: 9999px; background-color: rgba(148, 163, 184, 0.5); }
</style>
