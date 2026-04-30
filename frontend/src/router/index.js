import { createRouter, createWebHistory } from 'vue-router'
import axios from 'axios'
import { storage } from '@/utils/storage'
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
import TableSale from '../views/TableSale.vue'
import FloorManager from '../views/FloorManager.vue'
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

// ==================== FONCTIONS UTILITAIRES ====================

const ensureAdminAccess = async () => {
  const auth = storage.getAuth();
  if (!auth?.user) return false;
  
  if (auth.user.roles?.includes('admin')) return true;

  try {
    const { data } = await axios.get(`${API_BASE_URL}/users/${auth.user.id}/roles`, {
      headers: { Authorization: `Bearer ${auth.token}` },
    })

    const roles = (data?.data || data || []).map((role) => role.name)
    // Mettre à jour le stockage si les rôles ont changé
    storage.setAuth(auth.token, auth.user, roles, auth.user.permissions);
    return roles.includes('admin')
  } catch (error) {
    console.error('Erreur chargement des rôles:', error.response?.data || error.message)
    return false
  }
}

// === NOUVELLE FONCTION AJOUTÉE ===
const isCashPrinterRoute = (to) => {
  if (!to || !to.name) return false

  const printerRoutes = [
    'cash-printer',
    'cash-registers-machine-link',
    'cashier-dashboard', // si tu veux l'inclure
    'printers-create',
  ]

  return (
    printerRoutes.includes(to.name) ||
    to.path.includes('printer') ||
    to.path.includes('cash-printer')
  )
}

// ==================== ROUTER ====================

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'login', component: Login },
    { path: '/pos', name: 'pos', component: Pos },
    { path: '/direct', redirect: { name: 'dashboard-direct' } },

    // Dashboard avec routes enfants
    {
      path: '/dashboard',
      name: 'dashboard',
      component: Dashboard,
      children: [
        { path: '', name: 'dashboard-overview', component: DashboardOverview },
        { path: 'direct', name: 'dashboard-direct', component: DirectSale },
        {
          path: 'table',
          name: 'dashboard-table',
          component: FloorManager,
          props: { embedded: true },
        },
        {
          path: 'table/order/:tableId?',
          name: 'dashboard-table-order',
          component: TableSale,
          props: true,
        },
        {
          path: 'table/manage',
          name: 'dashboard-table-manage',
          component: TableManage,
          props: { embedded: true },
        },
        { path: 'product', name: 'dashboard-product', component: Product },
        { path: 'categories', name: 'dashboard-categories', component: CategoryManage },
        {
          path: 'ventes',
          name: 'dashboard-ventes',
          component: () => import('../views/SalesList.vue'),
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
        { path: 'printers', name: 'dashboard-printers', component: Printer },
        { 
          path: 'printers/test', 
          name: 'dashboard-printers-test', 
          component: () => import('../views/PrinterTest.vue') 
        },
        {
          path: 'roles',
          name: 'dashboard-roles',
          component: RoleList,
          meta: { requiresAdmin: true },
        },
        // ... tes autres routes enfants (roles, users, etc.)
        // --- GESTION DES RÔLES ---
        {
          path: 'roles',
          name: 'dashboard-roles',
          component: () => import('../views/roles/RoleList.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'roles/create',
          name: 'dashboard-roles-create',
          component: () => import('../views/roles/RoleCreate.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'roles/:id/edit',
          name: 'dashboard-roles-edit',
          component: () => import('../views/roles/RoleEdit.vue'),
          props: true,
          meta: { requiresAdmin: true },
        },

        // --- GESTION DES PERMISSIONS (Optionnel/Consultation) ---
        {
          path: 'permissions',
          name: 'dashboard-permissions',
          component: () => import('../views/permissions/PermissionList.vue'),
          meta: { requiresAdmin: true },
        },

        // --- GESTION DES UTILISATEURS ---
        {
          path: 'users',
          name: 'dashboard-users',
          component: () => import('../views/users/UserList.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'users/create',
          name: 'dashboard-users-create',
          component: () => import('../views/users/UserCreate.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'users/:id/edit',
          name: 'dashboard-users-edit',
          component: () => import('../views/users/UserEdit.vue'),
          props: true,
          meta: { requiresAdmin: true },
        },
      ],
    },

    { path: '/cash-printer', name: 'cash-printer', component: CashPrinter },
    {
      path: '/cash-registers/machine-link',
      name: 'cash-registers-machine-link',
      component: CashPrinter,
    },
    { path: '/billetage', name: 'billetage', component: () => import('../views/Billetage.vue') },
    {
      path: '/billetage/:sessionId/resume',
      name: 'billetage-summary',
      component: () => import('../views/BilletageSummary.vue'),
    },

    // Redirections
    { path: '/user-sales', redirect: { name: 'dashboard-user-sales' } },
    { path: '/retour', redirect: { name: 'dashboard-retour' } },
    { path: '/point-of-sale', redirect: { name: 'dashboard-point-of-sale' } },
    { path: '/cash-register-sessions', redirect: { name: 'dashboard-cash-register-sessions' } },
  ],
})

// ==================== GUARD GLOBAL ====================

router.beforeEach(async (to, from, next) => {
  // Ignorer la page login
  if (to.path === '/' || to.path === '/login') {
    next()
    return
  }

  // Vérification expiration token
  // Vérification expiration token via storage.getAuth() (gère l'expiration)
  const auth = storage.getAuth()
  if (!auth || !auth.token) {
    next('/')
    return
  }

  const cashRegisterRequiredRoutes = new Set([
    'dashboard-direct',
    'dashboard-table',
    'dashboard-table-order',
    'table',
    'table-sales',
    'direct',
  ])

  const requiresCashRegister = to.matched.some((record) =>
    cashRegisterRequiredRoutes.has(String(record.name)),
  )

  const requiresAdminAccess = to.matched.some((record) => record.meta?.requiresAdmin)
  const adminBypassSession = to.matched.some((record) => record.meta?.bypassSessionForAdmin)

  const sessionData =
    localStorage.getItem('cashRegisterSession') || localStorage.getItem('cash_register_session')
  const hasActiveSession = Boolean(sessionData)

  const verifyAdminAccess = async () => {
    return await ensureAdminAccess()
  }

  // === CORRECTION PRINCIPALE ICI ===
  if (requiresCashRegister && !hasActiveSession) {
    // Si c'est une route liée à l'imprimante/caisse, on autorise
    if (isCashPrinterRoute(to)) {
      next()
      return
    }

    // Si c'est un admin avec bypass
    if (adminBypassSession) {
      const isAdmin = await verifyAdminAccess()
      if (isAdmin) {
        next()
        return
      }
    }

    // Sinon → rediriger vers la connexion caisse
    next({ name: 'cash-printer' })
    return
  }

  // Vérification accès admin
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
