import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Dashboard      from '@/views/Dashboard.vue'
import Terminals      from '@/views/Terminals.vue'
import TerminalDetail from '@/views/TerminalDetail.vue'
import TerminalManage from '@/views/TerminalManage.vue'
import Login          from '@/views/Login.vue'

const routes = [
  { path: '/login', component: Login, name: 'login', meta: { public: true } },
  { path: '/',                    component: Dashboard,      name: 'dashboard',        meta: { requiresAuth: true } },
  { path: '/terminals',           component: Terminals,      name: 'terminals',        meta: { requiresAuth: true } },
  { path: '/terminals/:id',       component: TerminalDetail, name: 'terminal-detail',  meta: { requiresAuth: true } },
  { path: '/terminals-manage',    component: TerminalManage, name: 'terminal-manage',  meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }
  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
