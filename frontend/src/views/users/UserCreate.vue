<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Administration</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-plus-circle" class="text-indigo-500" />
          Créer un utilisateur
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Ajoutez un nouveau compte et rattachez-le aux points de vente appropriés.
        </p>
      </div>
      <router-link
        :to="{ name: 'dashboard-users' }"
        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
      >
        <font-awesome-icon icon="fa-solid fa-arrow-left" />
        Retour
      </router-link>
    </header>

    <div
      v-if="errors.general"
      class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-600"
    >
      <div class="flex items-center gap-2">
        <font-awesome-icon icon="fa-solid fa-triangle-exclamation" />
        <span>{{ errors.general }}</span>
      </div>
      <button
        type="button"
        class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-600 transition hover:bg-rose-100"
        @click="errors.general = ''"
      >
        Fermer
      </button>
    </div>

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-6 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-base font-semibold text-slate-800">Informations du compte</h2>
          <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">
            Nouveau profil
          </span>
        </div>
      </div>

      <form @submit.prevent="createUser">
        <div class="grid gap-6 px-6 py-6 sm:grid-cols-2">
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Email</label>
            <input
              v-model.trim="user.email"
              type="email"
              required
              placeholder="prenom.nom@entreprise.com"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-rose-300': errors.email }"
            />
            <p v-if="errors.email" class="text-xs font-semibold text-rose-500">{{ errors.email }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Nom complet</label>
            <input
              v-model.trim="user.name"
              type="text"
              required
              placeholder="Jean Dupont"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-rose-300': errors.name }"
            />
            <p v-if="errors.name" class="text-xs font-semibold text-rose-500">{{ errors.name }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Mot de passe</label>
            <input
              v-model="user.password"
              type="password"
              required
              placeholder="Minimum 8 caracteres"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-rose-300': errors.password }"
            />
            <p v-if="errors.password" class="text-xs font-semibold text-rose-500">{{ errors.password }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Confirmation du mot de passe</label>
            <input
              v-model="user.password_confirmation"
              type="password"
              required
              placeholder="Retapez votre mot de passe"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-rose-300': errors.password_confirmation }"
            />
            <p v-if="errors.password_confirmation" class="text-xs font-semibold text-rose-500">
              {{ errors.password_confirmation }}
            </p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Rôle</label>
            <select
              v-model="user.role"
              required
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-rose-300': errors.role }"
            >
              <option value="">Sélectionner un rôle</option>
              <option v-for="role in roles" :key="role.id" :value="role.name">
                {{ role.name }}
              </option>
            </select>
            <p v-if="errors.role" class="text-xs font-semibold text-rose-500">{{ errors.role }}</p>
          </div>

          <!-- Section Points de Vente (Multi-sélection) -->
          <div class="space-y-3 sm:col-span-2">
            <label class="text-sm font-medium text-slate-600 flex items-center justify-between">
              <span>Points de vente autorisés</span>
              <span class="text-[10px] uppercase font-black text-indigo-400">
                {{ user.point_of_sale_ids.length }} sélectionné(s)
              </span>
            </label>
            
            <div v-if="loadingPointsOfSale" class="py-4 text-center">
              <span class="h-6 w-6 animate-spin inline-block rounded-full border-2 border-slate-200 border-t-indigo-500"></span>
            </div>
            
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
              <label 
                v-for="pos in pointsOfSale" 
                :key="pos.id"
                class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer bg-white"
                :class="user.point_of_sale_ids.includes(pos.id) ? 'border-indigo-200 bg-indigo-50/50 ring-2 ring-indigo-50' : 'border-slate-100 hover:border-slate-200'"
              >
                <input 
                  type="checkbox" 
                  :value="pos.id" 
                  v-model="user.point_of_sale_ids"
                  class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                />
                <span class="text-xs font-bold text-slate-700">{{ pos.name }}</span>
              </label>
            </div>
            <p v-if="errors.point_of_sale_id" class="text-xs font-semibold text-rose-500">
              {{ errors.point_of_sale_id }}
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
          <router-link
            :to="{ name: 'dashboard-users' }"
            class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
          >
            <font-awesome-icon icon="fa-solid fa-xmark" />
            Annuler
          </router-link>
          <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="isCreating || !isFormValid"
          >
            <font-awesome-icon :icon="isCreating ? 'fa-solid fa-rotate' : 'fa-solid fa-plus-circle'" :class="{ 'animate-spin': isCreating }" />
            {{ isCreating ? 'Creation...' : 'Creer' }}
          </button>
        </div>
      </form>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import userService from '@/services/userService'
import roleService from '@/services/roleService'
import pointOfSaleService from '@/services/pointOfSaleService'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

defineOptions({ name: 'UserCreate' })

const router = useRouter()

const user = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: '',
  point_of_sale_ids: []
})

const errors = ref({})
const isCreating = ref(false)
const pointsOfSale = ref([])
const roles = ref([])
const loadingPointsOfSale = ref(false)

const isFormValid = computed(() => {
  return user.value.name.trim() &&
    user.value.email.trim() &&
    user.value.password.length >= 8 &&
    user.value.password === user.value.password_confirmation &&
    user.value.role &&
    user.value.point_of_sale_ids.length > 0
})

const fetchData = async () => {
  loadingPointsOfSale.value = true
  try {
    const [posRes, rolesRes] = await Promise.all([
      pointOfSaleService.getAll(),
      roleService.getAll()
    ])
    pointsOfSale.value = posRes.data?.data || posRes.data || posRes
    roles.value = rolesRes.data?.data || rolesRes.data || []
  } catch (error) {
    console.error('Erreur lors du chargement des données:', error)
  } finally {
    loadingPointsOfSale.value = false
  }
}

const createUser = async () => {
  errors.value = {}

  if (user.value.password.length < 8) {
    errors.value.password = 'Le mot de passe doit contenir au moins 8 caractères'
    return
  }

  try {
    isCreating.value = true
    await userService.create(user.value)
    router.push({ name: 'dashboard-users' })
  } catch (error) {
    console.error('Erreur lors de la création de l\'utilisateur:', error)
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      errors.value.general = 'Erreur lors de la création de l\'utilisateur. Veuillez réessayer.'
    }
  } finally {
    isCreating.value = false
  }
}

onMounted(fetchData)
</script>
