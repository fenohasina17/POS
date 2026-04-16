<template>
  <nav class="bg-slate-800 shadow-md px-4 py-2">
    <div class="flex flex-wrap items-center justify-center gap-4 md:gap-6">
      <router-link
        to="/pos"
        class="inline-flex items-center gap-2 rounded-md bg-red-500 px-3 py-2 font-semibold text-white hover:bg-red-600"
      >
        <font-awesome-icon icon="shopping-cart" />
        <span class="text-sm">POS</span>
      </router-link>

      <router-link
        to="/roles"
        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-white hover:bg-slate-700"
      >
        <font-awesome-icon icon="users" />
        <span class="text-sm">Roles</span>
      </router-link>

      <router-link
        to="/users"
        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-white hover:bg-slate-700"
      >
        <font-awesome-icon icon="user" />
        <span class="text-sm">Users</span>
      </router-link>

      <router-link
        to="/"
        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-white hover:bg-slate-700"
      >
        <font-awesome-icon icon="home" />
        <span class="text-sm">Home</span>
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
