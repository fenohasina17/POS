import './assets/main.css'
import 'flowbite';
import './services/echo' // <--- Initialisation WebSocket (Reverb)

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { FontAwesomeIcon } from './plugins/fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { 
  faUserGear, 
  faArrowLeft, 
  faTrash, 
  faTriangleExclamation,
  faTag,
  faLock,
  faFloppyDisk,
  faSpinner,
  faShieldHalved,
  faXmark,
  faCircleInfo,
  faCircleCheck,
  faPlus,
  faStore,
  faUserCheck,
  faUser,
  faPen,
  faTable,
  faRotate,
  faEye,
  faPrint,
  faSearch,
  faShoppingCart,
  faCheck,
  faTableList,
  faClock,
  faCheckCircle,
  faBoxes,
  faUsers,
  faUtensils,
  faLocationDot,
  faCalendarCheck
  } from '@fortawesome/free-solid-svg-icons'

  library.add(
  faUserGear, faArrowLeft, faTrash, faTriangleExclamation,
  faTag, faLock, faFloppyDisk, faSpinner, faShieldHalved,
  faXmark, faCircleInfo, faCircleCheck, faPlus,
  faStore, faUserCheck, faUser, faPen, faTable, faRotate,
  faEye, faPrint, faSearch, faShoppingCart, faCheck,
  faTableList, faClock, faCheckCircle, faBoxes, faUsers,
  faUtensils, faLocationDot, faCalendarCheck
  )
const app = createApp(App)

// Enregistre le composant font-awesome
app.component('font-awesome-icon', FontAwesomeIcon)

app.use(createPinia())
app.use(router)
app.mount('#app')
