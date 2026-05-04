<template>
  <div class="flex min-h-screen bg-slate-100 text-slate-900">
    <div
      class="fixed inset-0 z-30 bg-slate-900/40 transition-opacity duration-200 lg:hidden"
      :class="sidebarOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'"
      @click="closeSidebar"
    ></div>

    <aside
      :class="[
        'fixed inset-y-0 left-0 z-40 border-r border-slate-200 bg-white shadow-sm transition-all duration-200 ease-in-out',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        'lg:translate-x-0',
        sidebarCollapsed ? 'lg:w-16' : 'lg:w-56'
      ]"
    >
      <div class="flex h-full flex-col">
        <div
          class="flex items-center gap-2 px-4 pt-4 shrink-0"
          :class="isSidebarCollapsed ? 'lg:px-2 lg:justify-center' : ''"
        >
          <img
            src="../assets/logoigp.jpg"
            alt="Logo International Gastronomy Pizza"
            class="h-9 w-auto rounded-lg object-cover"
            :class="isSidebarCollapsed ? 'lg:h-8' : ''"
          />
          <div v-if="!isSidebarCollapsed" class="hidden lg:block">
            <p class="text-sm font-semibold text-slate-900">IGP POS</p>
            <p class="text-[10px] text-slate-400">Dashboard restaurants</p>
          </div>
        </div>

        <nav :class="['mt-12 h-12', isSidebarCollapsed ? 'px-1' : 'px-3']">
          <div v-for="section in navigationSections" :key="section.title" class="mb-6">
            <p
              class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400"
              :class="isSidebarCollapsed ? 'lg:hidden' : ''"
            >
              {{ section.title }}
            </p>
            <ul class="space-y-1">
              <li v-for="item in section.items" :key="item.label" class="space-y-1">
                <button
                  type="button"
                  :class="[
                    'flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium transition',
                    isActive(item) ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-slate-100',
                    isSidebarCollapsed ? 'lg:justify-center lg:gap-0 lg:px-0 lg:py-2' : ''
                  ]"
                  @click="handleNavigation(item)"
                >
                  <span
                    class="flex h-8 w-8 items-center justify-center rounded-lg"
                    :class="isActive(item) ? 'bg-white text-indigo-600 shadow-sm' : 'bg-slate-100 text-slate-500'"
                  >
                    <FontAwesomeIcon :icon="item.icon" class="text-sm" />
                  </span>
                  <span :class="['flex-1 text-left text-xs', isSidebarCollapsed ? 'lg:hidden' : '']">{{ item.label }}</span>
                  <FontAwesomeIcon
                    v-if="item.children && !isSidebarCollapsed"
                    :icon="faChevronDown"
                    :class="[
                      'text-[10px] text-slate-400 transition-transform',
                      isMenuExpanded(item) ? 'rotate-180 text-indigo-500' : ''
                    ]"
                  />
                </button>

                <transition name="fade">
                  <ul
                    v-if="item.children && isMenuExpanded(item) && !isSidebarCollapsed"
                    class="space-y-1 pl-10"
                  >
                    <li v-for="child in item.children" :key="child.label">
                      <button
                        type="button"
                        :class="[
                          'flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs transition',
                          isActive(child) ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-slate-100'
                        ]"
                        @click="handleNavigation(child)"
                      >
                        <span
                          class="flex h-7 w-7 items-center justify-center rounded-md"
                          :class="isActive(child) ? 'bg-white text-indigo-600 shadow-sm' : 'bg-slate-100 text-slate-500'"
                        >
                          <FontAwesomeIcon :icon="child.icon" class="text-xs" />
                        </span>
                        <span class="flex-1 text-left">{{ child.label }}</span>
                      </button>
                    </li>
                  </ul>
                </transition>
              </li>
            </ul>
          </div>
        </nav>
      </div>
    </aside>

    <div :class="['flex min-h-screen flex-1 flex-col', contentPaddingClass]">
      <header :class="['fixed top-0 right-0 left-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur', headerOffsetClass]">
        <div class="flex w-full flex-wrap items-center gap-3 px-3 py-1 sm:px-4 lg:px-6">
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
              @click="toggleSidebar"
              aria-label="Basculer le menu"
            >
              <FontAwesomeIcon :icon="faBars" class="text-sm" />
            </button>

            <!-- Header loading indicator -->
            <transition name="fade">
              <div v-if="globalLoading" class="ml-2 flex items-center gap-1.5 px-2 py-1 rounded-full bg-indigo-50/50">
                <div class="h-1.5 w-1.5 animate-bounce rounded-full bg-indigo-500"></div>
                <div class="h-1.5 w-1.5 animate-bounce rounded-full bg-indigo-500 [animation-delay:-0.15s]"></div>
                <div class="h-1.5 w-1.5 animate-bounce rounded-full bg-indigo-500 [animation-delay:-0.3s]"></div>
              </div>
            </transition>
          </div>

          <div class="flex flex-1 items-center justify-end gap-2">
            <button
              type="button"
              class="relative flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-indigo-200 hover:text-indigo-600"
              aria-label="Notifications"
            >
              <FontAwesomeIcon :icon="faBell" class="text-sm" />
              <span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-orange-400"></span>
            </button>
            <div ref="userMenuRef" class="relative flex items-center">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2 py-1 text-left text-sm font-medium text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600"
                @click="toggleUserMenu"
                aria-haspopup="true"
                :aria-expanded="userMenuOpen"
              >
                <img src="../assets/avatar.png" alt="User avatar" class="h-7 w-7 rounded-full object-cover" />
                <span class="hidden text-xs font-semibold text-slate-700 sm:inline">{{ userDisplayName }}</span>
                <FontAwesomeIcon :icon="faChevronDown" class="hidden text-[10px] text-slate-400 sm:inline" />
              </button>

              <transition name="fade">
                <div
                  v-if="userMenuOpen"
                  class="absolute right-0 top-10 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                >
                  <div class="border-b border-slate-100 px-3 py-2">
                    <p class="text-sm font-semibold text-slate-800">{{ userDisplayName }}</p>
                    <p class="text-[11px] text-slate-400">{{ userEmail }}</p>
                  </div>
                  <nav class="py-2 text-sm text-slate-600">
                    <button
                      type="button"
                      class="flex w-full items-center px-3 py-1.5 text-xs transition hover:bg-slate-50"
                      @click="navigateTo('dashboard-overview')"
                    >
                      Dashboard
                    </button>
                    <button type="button" class="flex w-full items-center px-3 py-1.5 text-xs text-slate-400" disabled>
                      Paramètres
                    </button>
                    <button type="button" class="flex w-full items-center px-3 py-1.5 text-xs text-slate-400" disabled>
                      Revenus
                    </button>
                    <button
                      type="button"
                      class="flex w-full items-center px-3 py-1.5 text-xs text-rose-600 transition hover:bg-rose-50"
                      @click="logout"
                    >
                      Déconnexion
                    </button>
                  </nav>
                </div>
              </transition>
            </div>
          </div>
        </div>
      </header>

      <main class="flex-1 px-3 pb-6 pt-[3.5rem] sm:px-4 lg:px-5 lg:pt-[4rem]">
        <router-view v-slot="{ Component }">
          <transition name="page-fade" mode="out-in">
            <div v-if="globalLoading" class="flex h-[60vh] flex-col items-center justify-center space-y-6">
              <div class="relative flex h-20 w-20 items-center justify-center">
                <div class="absolute inset-0 animate-spin rounded-full border-4 border-slate-100 border-t-indigo-600"></div>
                <img src="../assets/logoigp.jpg" alt="Loading" class="h-10 w-10 rounded-full shadow-sm" />
              </div>
              <div class="flex flex-col items-center gap-2">
                <p class="animate-pulse text-base font-semibold text-slate-700">Préparation de votre session...</p>
                <p class="text-xs text-slate-400">Chargement des données en cours</p>
              </div>
            </div>
            <component v-else :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterView } from 'vue-router'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faArrowRotateLeft,
  faBars,
  faBell,
  faBoxesStacked,
  faCashRegister,
  faClipboardList,
  faChevronDown,
  faGear,
  faGaugeHigh,
  faMagnifyingGlass,
  faMoon,
  faPrint,
  faReceipt,
  faStore,
  faTableCellsLarge,
  faUsersGear,
  faKey,
  faUserGroup,
  faChartLine,
  faLayerGroup,
  faListCheck
} from '@fortawesome/free-solid-svg-icons'
import { useAuth } from '@/composables/useAuth'
import { useCategories } from '@/composables/useCategories'
import { storage } from '@/utils/storage'

defineOptions({ name: 'DashboardLayout' })

const router = useRouter()
const route = useRoute()

const sidebarOpen = ref(false)
const sidebarCollapsed = ref(false)
const searchQuery = ref('')
const userMenuOpen = ref(false)
const userMenuRef = ref(null)
const expandedMenus = ref(new Set())
const globalLoading = ref(true)

const isDesktop = ref(false)

const { user, isAdmin, hasRole, loadUserData } = useAuth()
const { loadCategories } = useCategories()

const isSidebarCollapsed = computed(() => sidebarCollapsed.value && isDesktop.value)

const headerOffsetClass = computed(() => {
  if (!isDesktop.value) return ''
  return sidebarCollapsed.value ? 'lg:left-16' : 'lg:left-56'
})

const contentPaddingClass = computed(() => {
  if (!isDesktop.value) return ''
  return sidebarCollapsed.value ? 'lg:pl-16' : 'lg:pl-56'
})

const setInitialSidebarState = () => {
  if (typeof window === 'undefined') return
  isDesktop.value = window.innerWidth >= 1024
  sidebarOpen.value = isDesktop.value
}

const handleDocumentClick = (event) => {
  if (!userMenuRef.value) return
  if (userMenuRef.value.contains(event.target)) return
  userMenuOpen.value = false
}

const handleResize = () => {
  if (typeof window === 'undefined') return
  const width = window.innerWidth
  isDesktop.value = width >= 1024
  sidebarOpen.value = isDesktop.value
}

onMounted(async () => {
  setInitialSidebarState()

  try {
    // Parallelize initialization of user data and categories/products
    await Promise.all([
      loadUserData(),
      loadCategories()
    ])
  } catch (error) {
    console.error('Initialisation Dashboard échouée:', error)
  } finally {
    globalLoading.value = false
  }

  document.addEventListener('click', handleDocumentClick)
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', handleResize)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick)
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', handleResize)
  }
})

const filterAdminItems = (items) => {
  return items
    .filter((item) => {
      if (item.adminOnly && !isAdmin.value) return false
      if (item.caissierOnly && !hasRole('caissier') && !isAdmin.value) return false
      return true
    })
    .map((item) =>
      item.children
        ? {
            ...item,
            children: item.children.filter((child) => {
              if (child.adminOnly && !isAdmin.value) return false
              if (child.caissierOnly && !hasRole('caissier') && !isAdmin.value) return false
              return true
            }),
          }
        : item,
    )
}

const navigationSections = computed(() => {
  const menuItems = filterAdminItems([
    { label: 'Dashboard', name: 'dashboard-overview', icon: faGaugeHigh },
    { label: 'Vente directe', name: 'dashboard-direct', icon: faCashRegister },
    {
      label: 'Service Salle',
      name: 'service-salle',
      icon: faTableCellsLarge,
      children: [
        { label: 'Salle', name: 'dashboard-table', icon: faTableCellsLarge },
        { label: 'Gestion des tables', name: 'dashboard-table-manage', icon: faListCheck },
      ],
    },
    { label: 'Produits', name: 'dashboard-product', icon: faBoxesStacked, adminOnly: true },
    { label: 'Catégories', name: 'dashboard-categories', icon: faLayerGroup, adminOnly: true },
    { label: 'Ventes', name: 'dashboard-ventes', icon: faChartLine, adminOnly: true },
    { label: 'Mes ventes', name: 'dashboard-user-sales', icon: faReceipt },
    { label: 'Remise à zéro', name: 'dashboard-retour', icon: faArrowRotateLeft, caissierOnly: true },
  ])

  const toolItems = filterAdminItems([
    { label: 'Point de vente', name: 'dashboard-point-of-sale', icon: faStore, adminOnly: true },
    { label: 'Caisse', name: 'dashboard-cash-register-sessions', icon: faClipboardList, adminOnly: true },
    { label: 'Utilisateurs', name: 'dashboard-users', icon: faUserGroup, adminOnly: true },
    ...(isAdmin.value
      ? [
          {
            label: 'Rôles',
            name: 'dashboard-roles',
            icon: faUsersGear,
            names: ['dashboard-roles', 'dashboard-roles-create', 'dashboard-roles-edit'],
          },
          {
            label: 'Permissions',
            name: 'dashboard-permissions',
            icon: faKey,
            names: ['dashboard-permissions', 'dashboard-permissions-create'],
          },
        ]
      : []),
  ])

  const sections = [
    { title: 'Menu', items: menuItems },
    { title: 'Outils', items: toolItems },
  ]

  return sections.filter((section) => section.items.length > 0)
})

const toggleSidebar = () => {
  if (!isDesktop.value) {
    sidebarOpen.value = !sidebarOpen.value
    return
  }
  sidebarCollapsed.value = !sidebarCollapsed.value
}

const closeSidebar = () => {
  if (isDesktop.value) return
  sidebarOpen.value = false
}

const closeUserMenu = () => {
  userMenuOpen.value = false
}

const toggleUserMenu = () => {
  userMenuOpen.value = !userMenuOpen.value
}

const toggleMenu = (item) => {
  if (!item?.name) return
  const updated = new Set(expandedMenus.value)
  if (updated.has(item.name)) {
    updated.delete(item.name)
  } else {
    updated.add(item.name)
  }
  expandedMenus.value = updated
}

const isMenuExpanded = (item) => {
  if (!item?.name) return false
  return expandedMenus.value.has(item.name)
}

const navigateTo = (name) => {
  closeSidebar()
  closeUserMenu()
  router.push({ name })
}

const handleNavigation = (item) => {
  if (item?.children?.length) {
    toggleMenu(item)
    return
  }

  closeSidebar()
  closeUserMenu()
  if (item?.name) {
    router.push({ name: item.name })
  }
}

const logout = () => {
  closeUserMenu()
  storage.clearAuth()
  storage.clearSession()
  router.push({ name: 'login' })
}

const isActive = (item) => {
  const currentName = route.name ? route.name.toString() : ''
  if (item.children?.length) {
    return item.children.some((child) => isActive(child))
  }
  if (item.matchPrefix) {
    return currentName.startsWith(item.matchPrefix)
  }
  if (Array.isArray(item.names)) {
    return item.names.includes(currentName)
  }
  if (item.name) {
    return currentName === item.name
  }
  return false
}

const expandMenuForRoute = () => {
  const currentName = route.name ? route.name.toString() : ''
  const updated = new Set(expandedMenus.value)

  navigationSections.value.forEach((section) => {
    section.items.forEach((item) => {
      if (!item.children?.length || !item.name) return
      const matches = item.children.some((child) => {
        if (child.matchPrefix) {
          return currentName.startsWith(child.matchPrefix)
        }
        if (Array.isArray(child.names)) {
          return child.names.includes(currentName)
        }
        return child.name === currentName
      })
      if (matches) {
        updated.add(item.name)
      }
    })
  })

  expandedMenus.value = updated
}

watch(
  () => route.name,
  () => {
    expandMenuForRoute()
  },
  { immediate: true }
)

const currentPageTitle = computed(() => {
  const currentName = route.name ? route.name.toString() : ''
  const titles = {
    'dashboard-overview': 'Dashboard',
    'dashboard-direct': 'Vente directe',
    'dashboard-table': 'Salle',
    'dashboard-table-manage': 'Gestion des tables',
    'dashboard-product': 'Produits',
    'dashboard-ventes': 'Ventes',
    'dashboard-user-sales': 'Mes ventes',
    'dashboard-retour': 'Remise à zéro',
    'dashboard-printers': 'Imprimantes',
    'dashboard-cash-register-sessions': 'Sessions caisse',
    'dashboard-point-of-sale': 'Point de vente',
    'dashboard-roles': 'Gestion des rôles',
    'dashboard-roles-create': 'Créer un rôle',
    'dashboard-roles-edit': 'Modifier un rôle',
    'dashboard-permissions': 'Permissions',
    'dashboard-permissions-create': 'Créer une permission',
    'dashboard-users': 'Utilisateurs',
    'dashboard-users-create': 'Créer un utilisateur',
    'dashboard-users-edit': 'Modifier un utilisateur',
    'dashboard-users-roles': 'Rôles utilisateur',
  }
  return titles[currentName] || 'Dashboard'
})

const userDisplayName = computed(() => user.value?.name || 'Utilisateur')
const userEmail = computed(() => user.value?.email || 'user@example.com')
</script>

<style scoped>
aside::-webkit-scrollbar {
  width: 4px;
}

aside::-webkit-scrollbar-thumb {
  border-radius: 9999px;
  background-color: rgba(148, 163, 184, 0.5);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

.page-fade-enter-active,
.page-fade-leave-active {
  transition: opacity 0.4s ease;
}
.page-fade-enter-from,
.page-fade-leave-to {
  opacity: 0;
}
</style>
