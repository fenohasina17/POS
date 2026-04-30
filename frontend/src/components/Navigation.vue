<template>
  <nav class="bg-slate-800 shadow-md px-2 h-8 flex items-center justify-center">
    <div class="flex flex-wrap items-center justify-center gap-4 md:gap-6">
      <router-link
        to="/pos"
        class="inline-flex items-center gap-1 rounded-lg bg-red-500 px-2 py-0.5 font-semibold text-white hover:bg-red-600 text-[10px]"
      >
        <font-awesome-icon icon="shopping-cart" />
        <span>POS</span>
      </router-link>

      <router-link
        to="/roles"
        class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-white hover:bg-slate-700 text-[10px]"
      >
        <font-awesome-icon icon="users" />
        <span>Roles</span>
      </router-link>

      <router-link
        to="/users"
        class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-white hover:bg-slate-700 text-[10px]"
      >
        <font-awesome-icon icon="user" />
        <span>Users</span>
      </router-link>

      <router-link
        to="/"
        class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-white hover:bg-slate-700 text-[10px]"
      >
        <font-awesome-icon icon="home" />
        <span>Home</span>
      </router-link>

      <!-- POS Selector for Admins -->
      <div v-if="true" class="inline-flex items-center">
        <select
          v-model="selectedPOS"
          @change="onPOSChange"
          required
          class="rounded-md border border-slate-700 bg-slate-700 px-2 py-1 text-sm text-white outline-none focus:border-red-500"
        >
          <option :value="null" disabled>Sélectionner un POS</option>
          <option v-for="pos in pointOfSales" :key="pos.id" :value="pos">
            {{ pos.name }}
          </option>
        </select>
      </div>
    </div>
  </nav>
  
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { usePosStore } from '../stores/posStore.js'
import { useAuth } from '../composables/useAuth.js'

const posStore = usePosStore()
const { isAdmin, loadUserData } = useAuth()

const selectedPOS = ref(null)
const pointOfSales = ref([])

onMounted(async () => {
  // Load user data to determine if admin
  await loadUserData()

  if (isAdmin.value) {
    await posStore.fetchPointOfSales()
    pointOfSales.value = posStore.pointOfSales
    posStore.loadSelectedPOS()
    selectedPOS.value = posStore.selectedPOS
  }
})

const onPOSChange = () => {
  posStore.setSelectedPOS(selectedPOS.value)
}
</script>
