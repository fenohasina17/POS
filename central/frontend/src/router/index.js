import { createRouter, createWebHistory } from 'vue-router'
import Dashboard from '@/views/Dashboard.vue'
import Terminals from '@/views/Terminals.vue'
import TerminalDetail from '@/views/TerminalDetail.vue'

const routes = [
  { path: '/',                     component: Dashboard,      name: 'dashboard' },
  { path: '/terminals',            component: Terminals,      name: 'terminals' },
  { path: '/terminals/:id',        component: TerminalDetail, name: 'terminal-detail' },
]

export default createRouter({
  history: createWebHistory(),
  routes,
})
