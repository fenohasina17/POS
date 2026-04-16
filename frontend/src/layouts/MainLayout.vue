import CashPrinter from '../views/CashPrinter.vue'
import Direct from '../views/Direct.vue'
import Product from '../views/Product.vue'
import Profile from '../views/Profile.vue'

const routes = [
{
path: '/cash-printer',
name: 'cash-printer',
component: CashPrinter
},
{
path: '/direct',
name: 'direct',
component: Direct
},
{
path: '/product',
name: 'product',
component: Product
},
{
path: '/profile',
name: 'profile',
component: Profile
}
]

const router = createRouter({
history: createWebHistory(import.meta.env.BASE_URL),
routes
})

export default router
import { createRouter, createWebHistory } from 'vue-router'
import CashPrinter from '../views/CashPrinter.vue'
import Direct from '../views/Direct.vue'
import Product from '../views/Product.vue'
import Profile from '../views/Profile.vue'

const routes = [
{
path: '/cash-printer',
name: 'cash-printer',
component: CashPrinter
},
{
path: '/direct',
name: 'direct',
component: Direct
},
{
path: '/product',
name: 'product',
component: Product
},
{
path: '/profile',
name: 'profile',
component: Profile
},
{
path: '/',
redirect: '/cash-printer'
}
]

const router = createRouter({
history: createWebHistory(import.meta.env.BASE_URL),
routes
})

export default router
