import { createRouter, createWebHistory } from 'vue-router'
import axios from 'axios'
import Login from '../views/Login.vue'
import DirectSale from '../views/DirectSale.vue'
import Pos from '../views/Pos.vue'

import Product from '../views/Product.vue'
import CashPrinter from '../views/CashPrinter.vue'
import UserSales from '../views/UserSales.vue'
import PointOfSaleManage from '../views/PointOfSaleManage.vue'
import CategoryManage from '../views/CategoryManage.vue'
import Dashboard from '../views/Dashboard.vue'
import DashboardOverview from '../views/DashboardOverview.vue'
import TableSales from '../views/TableSales.vue'
import TableSale from '../views/TableSale.vue'
import TableManage from '../views/TableManage.vue'

import RoleList from '@/views/roles/RoleList.vue'
import RoleCreate from '@/views/roles/RoleCreate.vue'
import RoleEdit from '@/views/roles/RoleEdit.vue'
import PermissionList from '@/views/permissions/PermissionList.vue'
import PermissionCreate from '@/views/permissions/PermissionCreate.vue'
import UserList from '@/views/users/UserList.vue'
import UserRoleManagement from '@/views/users/UserRoleManagement.vue'
import Printer from '../views/Printer.vue'
import { API_BASE_URL } from '@/utils/api'

// Fonction de vérification de l'expiration du token
function checkTokenExpiration() {
  const token = localStorage.getItem('token')
  const tokenExpiration = localStorage.getItem('token_expiration')

  if (token && tokenExpiration) {
    const currentTime = new Date().getTime()
    if (currentTime > parseInt(tokenExpiration)) {
      // Token expiré, nettoyer le localStorage
      localStorage.removeItem('token')
      localStorage.removeItem('token_expiration')
      localStorage.removeItem('user')
      localStorage.removeItem('user_expiration')
      localStorage.removeItem('user_roles')
      localStorage.removeItem('user_permissions')
      return false
    }
    return true
  }
  return false
}

const readStoredRoles = () => {
  try {
    const raw = localStorage.getItem('user_roles')
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

const fetchRolesFromApi = async () => {
  try {
    const userStr = localStorage.getItem('user')
    const token = localStorage.getItem('token')
    if (!userStr || !token) return []
    const parsedUser = JSON.parse(userStr)
    const userId = parsedUser?.id
    if (!userId) return []

    const { data } = await axios.get(`${API_BASE_URL}/users/${userId}/roles`, {
      headers: { Authorization: `Bearer ${token}` },
    })

    const roles = (data?.data || data || []).map((role) => role.name)
    localStorage.setItem('user_roles', JSON.stringify(roles))
    return roles
  } catch (error) {
    console.error('Erreur chargement des rôles:', error.response?.data || error.message)
    return []
  }
}

const ensureAdminAccess = async () => {
  const storedRoles = readStoredRoles()
  if (storedRoles.includes('admin')) {
    return true
  }

  const fetchedRoles = await fetchRolesFromApi()
  return fetchedRoles.includes('admin')
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'login',
      component: Login,
    },
    {
      path: '/pos',
      name: 'pos',
      component: Pos,
    },
    {
      path: '/direct',
      redirect: { name: 'dashboard-direct' },
    },
    {
      path: '/table',
      name: 'table',
      component: () => import('../views/TableSales.vue'),
    },
    {
      path: '/table-sales',
      name: 'table-sales',
      component: () => import('../views/TableSales.vue'),
    },
    {
      path: '/table/order/:tableId?',
      redirect: (to) => ({ name: 'dashboard-table-order', params: to.params }),
    },
    {
      path: '/tables/manage',
      name: 'tables-manage',
      component: () => import('../views/TableManage.vue'),
    },
    {
      path: '/tables/layout',
      name: 'tables-layout',
      component: () => import('../views/TableLayout.vue'),
    },
    {
      path: '/tables/selector',
      name: 'tables-selector',
      component: () => import('../views/TableSelector.vue'),
      redirect: { name: 'dashboard-table' },
    },
    {
      path: '/product',
      redirect: { name: 'dashboard-product' },
    },
    // Dashboard with nested tabs
    {
      path: '/dashboard',
      name: 'dashboard',
      component: Dashboard,
      children: [
        {
          path: '',
          name: 'dashboard-overview',
          component: DashboardOverview,
        },
        {
          path: 'direct',
          name: 'dashboard-direct',
          component: DirectSale,
        },
        {
          path: 'table',
          name: 'dashboard-table',
          component: TableSales,
          props: { embedded: true },
        },
        {
          path: 'table/order/:tableId?',
          name: 'dashboard-table-order',
          component: TableSale,
          props: (route) => ({
            tableId: route.params.tableId,
            embedded: true,
          }),
        },
        {
          path: 'table/manage',
          name: 'dashboard-table-manage',
          component: TableManage,
          props: { embedded: true },
        },
        {
          path: 'product',
          name: 'dashboard-product',
          component: Product,
        },
        {
          path: 'categories',
          name: 'dashboard-categories',
          component: CategoryManage,
        },
        {
          path: 'ventes',
          name: 'dashboard-ventes',
          component: () => import('../views/SalesList.vue'),
          props: { embedded: true },
          meta: { requiresAdmin: true, bypassSessionForAdmin: true },
        },
        {
          path: 'user-sales',
          name: 'dashboard-user-sales',
          component: UserSales,
          props: { embedded: true },
        },
        {
          path: 'retour',
          name: 'dashboard-retour',
          component: () => import('../views/Billetage.vue'),
        },
        {
          path: 'point-of-sale',
          name: 'dashboard-point-of-sale',
          component: PointOfSaleManage,
          meta: { requiresAdmin: true },
        },
        {
          path: 'cash-register-sessions',
          name: 'dashboard-cash-register-sessions',
          component: () => import('../views/CashRegisterSessions.vue'),
        },
        {
          path: 'printers',
          name: 'dashboard-printers',
          component: Printer,
        },
        {
          path: 'roles',
          name: 'dashboard-roles',
          component: RoleList,
          meta: { requiresAdmin: true },
        },
        {
          path: 'roles/create',
          name: 'dashboard-roles-create',
          component: RoleCreate,
          meta: { requiresAdmin: true },
        },
        {
          path: 'roles/:id/edit',
          name: 'dashboard-roles-edit',
          component: RoleEdit,
          props: true,
          meta: { requiresAdmin: true },
        },
        {
          path: 'permissions',
          name: 'dashboard-permissions',
          component: PermissionList,
          meta: { requiresAdmin: true },
        },
        {
          path: 'permissions/create',
          name: 'dashboard-permissions-create',
          component: PermissionCreate,
          meta: { requiresAdmin: true },
        },
        {
          path: 'users',
          name: 'dashboard-users',
          component: UserList,
          meta: { requiresAdmin: true },
        },
        {
          path: 'users/create',
          name: 'dashboard-users-create',
          component: () => import('@/views/users/UserCreate.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'users/:id/edit',
          name: 'dashboard-users-edit',
          component: () => import('@/views/users/UserEdit.vue'),
          props: true,
          meta: { requiresAdmin: true },
        },
        {
          path: 'users/:userId/roles',
          name: 'dashboard-users-roles',
          component: UserRoleManagement,
          props: true,
          meta: { requiresAdmin: true },
        },
      ],
    },
    {
      path: '/cash-printer',
      name: 'cash-printer',
      component: CashPrinter,
    },
    {
      path: '/cashier-dashboard',
      name: 'cashier-dashboard',
      component: () => import('../views/CashierDashboard.vue'),
    },

    {
      path: '/user-sales',
      redirect: { name: 'dashboard-user-sales' },
    },
    {
      path: '/retour',
      redirect: { name: 'dashboard-retour' },
    },
    {
      path: '/point-of-sale',
      redirect: { name: 'dashboard-point-of-sale' },
    },
    {
      path: '/cash-register-sessions',
      redirect: { name: 'dashboard-cash-register-sessions' },
    },
    {
      path: '/cash-registers/machine-link',
      name: 'cash-registers-machine-link',
      component: () => import('../views/CashRegisterMachineView.vue'),
    },
    { path: '/roles', redirect: { name: 'dashboard-roles' } },
    { path: '/roles/create', redirect: { name: 'dashboard-roles-create' } },
    {
      path: '/roles/:id/edit',
      redirect: (to) => ({ name: 'dashboard-roles-edit', params: to.params }),
    },
    { path: '/permissions', redirect: { name: 'dashboard-permissions' } },
    { path: '/permissions/create', redirect: { name: 'dashboard-permissions-create' } },
    {
      path: '/users',
      redirect: { name: 'dashboard-users' },
    },
    {
      path: '/users/create',
      redirect: { name: 'dashboard-users-create' },
    },
    {
      path: '/users/:id/edit',
      redirect: (to) => ({ name: 'dashboard-users-edit', params: to.params }),
    },
    {
      path: '/users/:userId/roles',
      redirect: (to) => ({ name: 'dashboard-users-roles', params: to.params }),
    },
    {
      path: '/printers',
      redirect: { name: 'dashboard-printers' },
    },
    {
      path: '/printers/create',
      name: 'printers-create',
      component: () => import('../views/PrinterCreateView.vue'),
    },
    {
      path: '/categories',
      redirect: { name: 'dashboard-categories' },
    },
    {
      path: '/cash-transactions',
      name: 'cash-transactions',
      component: () => import('../views/CashTransactions.vue'),
    },
    {
      path: '/billetage',
      name: 'billetage',
      component: () => import('../views/Billetage.vue'),
    },
    {
      path: '/billetage/:sessionId/resume',
      name: 'billetage-summary',
      component: () => import('../views/BilletageSummary.vue'),
    },
  ],
})

// Vérification de l'expiration du token avant chaque navigation
router.beforeEach(async (to, from, next) => {
  // Ne pas vérifier pour la page de login
  if (to.path === '/' || to.path === '/login') {
    next()
    return
  }

  // Vérifier l'expiration du token
  if (!checkTokenExpiration()) {
    // Token expiré, rediriger vers la page de login
    next('/')
    return
  }

  const cashRegisterRequiredRoutes = new Set([
    'dashboard-direct',
    'dashboard-table',
    'dashboard-table-manage',
    'dashboard-product',
    'dashboard-ventes',
    'dashboard-user-sales',
    'table',
    'table-sales',
    'dashboard-table-order',
    'tables-manage',
    'tables-layout',
    'tables-selector',
    'direct',
    'product',
    'user-sales'
  ])

  const isCashPrinterRoute = to.name === 'cash-printer' || to.path.startsWith('/cash-printer')
  const requiresCashRegister = to.matched.some((record) => cashRegisterRequiredRoutes.has(String(record.name)))
  const requiresAdminAccess = to.matched.some((record) => record.meta?.requiresAdmin)
  const adminBypassSession = to.matched.some((record) => record.meta?.bypassSessionForAdmin)
  const sessionData = localStorage.getItem('cashRegisterSession') || localStorage.getItem('cash_register_session')
  const hasActiveSession = Boolean(sessionData)

  let adminCheckResult = null
  const verifyAdminAccess = async () => {
    if (adminCheckResult === null) {
      adminCheckResult = await ensureAdminAccess()
    }
    return adminCheckResult
  }

  if (requiresCashRegister && !hasActiveSession && !isCashPrinterRoute) {
    if (adminBypassSession && (await verifyAdminAccess())) {
      next()
      return
    }
    next({ name: 'cash-printer' })
    return
  }

  if (requiresAdminAccess) {
    const isAdminUser = await verifyAdminAccess()
    if (!isAdminUser) {
      next({ name: 'dashboard-overview' })
      return
    }
  }

  next()
})

export default router
