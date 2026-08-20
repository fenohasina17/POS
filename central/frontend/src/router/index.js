import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import MainLayout     from '@/layouts/MainLayout.vue'
import Dashboard      from '@/views/Dashboard.vue'
import Terminals      from '@/views/Terminals.vue'
import TerminalDetail from '@/views/TerminalDetail.vue'
import TerminalManage from '@/views/TerminalManage.vue'
import Sales          from '@/views/Sales.vue'
import Sessions       from '@/views/Sessions.vue'
import ProductReport  from '@/views/ProductReport.vue'
import Users          from '@/views/Users.vue'
import Login          from '@/views/Login.vue'

const routes = [
  { path: '/login', component: Login, name: 'login', meta: { public: true } },
  {
    path: '/',
    component: MainLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '',                   component: Dashboard,      name: 'dashboard'       },
      { path: 'terminals',          component: Terminals,      name: 'terminals'       },
      { path: 'terminals/:id',      component: TerminalDetail, name: 'terminal-detail' },
      { path: 'terminals-manage',   component: TerminalManage, name: 'terminal-manage' },
      { path: 'sales',              component: Sales,          name: 'sales'           },
      { path: 'sessions',           component: Sessions,       name: 'sessions'        },
      { path: 'products',           component: ProductReport,  name: 'product-report'  },
      { path: 'users',              component: Users,          name: 'users'           },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) return { name: 'login' }
  if (to.name === 'login' && auth.isAuthenticated)   return { name: 'dashboard' }
})

export default router
