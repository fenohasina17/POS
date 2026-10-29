<template>
  <div class="space-y-6">
    <header class="ui-page-header">
      <div>
        <p class="ui-page-eyebrow">Administration</p>
        <h1 class="ui-page-title mt-3 flex items-center gap-2">
          <font-awesome-icon icon="fa-solid fa-user-pen" class="text-primary-500" />
          Modifier l’utilisateur
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Ajustez les informations du compte et rattachez-le aux rôles et points de vente appropriés.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <router-link
          :to="{ name: 'dashboard-users' }"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-primary-200 hover:text-primary-600"
        >
          <font-awesome-icon icon="fa-solid fa-arrow-left" />
          Retour
        </router-link>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-primary-200 hover:text-primary-600"
          @click="resetForm"
          :disabled="loading"
        >
          <font-awesome-icon icon="fa-solid fa-rotate" />
          Réinitialiser
        </button>
      </div>
    </header>

    <div
      v-if="loading"
      class="ui-card flex flex-col items-center justify-center gap-3 px-6 py-16 text-center text-sm text-slate-500"
    >
      <span class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-primary-500"></span>
      <div>
        <p class="font-semibold text-slate-700">Chargement de l’utilisateur…</p>
        <p class="text-xs text-slate-400">Nous récupérons les informations du profil.</p>
      </div>
    </div>

    <div v-else class="space-y-6">
      <div
        v-if="error"
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-danger-200 bg-danger-50 px-5 py-4 text-sm text-danger-600"
      >
        <div class="flex items-center gap-2">
          <font-awesome-icon icon="fa-solid fa-triangle-exclamation" />
          <span>{{ error }}</span>
        </div>
        <button
          type="button"
          class="rounded-full border border-danger-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-danger-600 transition hover:bg-danger-100"
          @click="error = ''"
        >
          Fermer
        </button>
      </div>

      <div
        v-if="success"
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-success-200 bg-success-50 px-5 py-4 text-sm text-success-600"
      >
        <div class="flex items-center gap-2">
          <font-awesome-icon icon="fa-solid fa-circle-check" />
          <span>{{ success }}</span>
        </div>
        <button
          type="button"
          class="rounded-full border border-success-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-success-600 transition hover:bg-success-100"
          @click="success = ''"
        >
          Fermer
        </button>
      </div>

      <section class="ui-card">
        <div class="grid gap-6 px-6 py-6 sm:grid-cols-2">
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Nom d’utilisateur</label>
            <input
              v-model="user.name"
              type="text"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
              placeholder="Entrez le nom de l’utilisateur"
              :class="{ 'border-danger-300': errors.name }"
            />
            <p v-if="errors.name" class="text-xs font-semibold text-danger-500">{{ errors.name }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Email</label>
            <input
              v-model="user.email"
              type="email"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
              placeholder="prenom.nom@entreprise.com"
              :class="{ 'border-danger-300': errors.email }"
            />
            <p v-if="errors.email" class="text-xs font-semibold text-danger-500">{{ errors.email }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Nouveau mot de passe</label>
            <input
              v-model="user.password"
              type="password"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
              placeholder="Laisser vide pour ne pas changer"
              :class="{ 'border-danger-300': errors.password }"
            />
            <p v-if="errors.password" class="text-xs font-semibold text-danger-500">{{ errors.password }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Confirmer le mot de passe</label>
            <input
              v-model="user.password_confirmation"
              type="password"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
              placeholder="Confirmez le mot de passe"
              :class="{ 'border-danger-300': errors.password_confirmation }"
            />
            <p v-if="errors.password_confirmation" class="text-xs font-semibold text-danger-500">
              {{ errors.password_confirmation }}
            </p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Rôle</label>
            <select
              v-model="user.role"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
              :class="{ 'border-danger-300': errors.role }"
            >
              <option value="">Sélectionner un rôle</option>
              <option v-for="role in roles" :key="role.id" :value="role.name">
                {{ role.name }}
              </option>
            </select>
            <p v-if="errors.role" class="text-xs font-semibold text-danger-500">{{ errors.role }}</p>
          </div>

          <!-- Section Points de Vente (Multi-sélection) -->
          <div class="space-y-3 sm:col-span-2">
            <label class="text-sm font-medium text-slate-600 flex items-center justify-between">
              <span>Points de vente autorisés</span>
              <span class="text-[10px] uppercase font-black text-primary-400">
                {{ user.point_of_sale_ids?.length || 0 }} sélectionné(s)
              </span>
            </label>
            
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
              <label 
                v-for="pos in pointsOfSale" 
                :key="pos.id"
                class="flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer bg-white"
                :class="user.point_of_sale_ids?.includes(pos.id) ? 'border-primary-200 bg-primary-50/50 ring-2 ring-primary-50' : 'border-slate-100 hover:border-slate-200'"
              >
                <input 
                  type="checkbox" 
                  :value="pos.id" 
                  v-model="user.point_of_sale_ids"
                  class="rounded border-slate-200 text-primary-600 focus:ring-primary-500 h-4 w-4"
                />
                <span class="text-xs font-bold text-slate-700">{{ pos.name }}</span>
              </label>
            </div>
            <p v-if="errors.point_of_sale_id" class="text-xs font-semibold text-danger-500">
              {{ errors.point_of_sale_id }}
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
          <button
            type="button"
            class="ui-btn ui-btn-primary"
            @click="updateUser"
            :disabled="loading || !isFormValid"
          >
            <font-awesome-icon :icon="loading ? 'fa-solid fa-spinner' : 'fa-solid fa-floppy-disk'" :class="{ 'animate-spin': loading }" />
            {{ loading ? 'Mise à jour…' : 'Mettre à jour' }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import userService from '@/services/userService'
import roleService from '@/services/roleService'
import pointOfSaleService from '@/services/pointOfSaleService'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

defineOptions({ name: 'UserEdit', components: { FontAwesomeIcon } })

const route = useRoute()
const router = useRouter()

const roles = ref([])
const pointsOfSale = ref([])
const loading = ref(false)
const error = ref('')
const success = ref('')
const originalUser = ref({})
const errors = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: '',
  point_of_sale_id: ''
})
const user = ref({
  id: null,
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: '',
  point_of_sale_id: null,
  point_of_sale_ids: []
})

const loadUser = async () => {
  loading.value = true
  error.value = ''

  try {
    const userId = route.params.id
    const [userResponse, rolesResponse] = await Promise.all([
      userService.getUser(userId),
      roleService.getAll()
    ])

    const userData = userResponse.data;
    user.value = { 
      ...userData, 
      password: '', 
      password_confirmation: '',
      point_of_sale_ids: userData.points_of_sale?.map(p => p.id) || [],
      role: userData.roles?.[0]?.name || userData.roles?.[0] || ''
    }
    originalUser.value = { ...user.value }

    // Correction de l'affichage du rôle
    const allRoles = rolesResponse.data?.data || rolesResponse.data || []
    roles.value = Array.isArray(allRoles) ? allRoles : []

    const posResponse = await pointOfSaleService.getAll()
    console.log('POS Response:', posResponse);
    // Dans votre service, .then(res => res.data) est déjà fait. 
    // Donc posResponse contient déjà le tableau ou l'objet {data: [...]}
    const posResData = posResponse.data || posResponse || []
    pointsOfSale.value = Array.isArray(posResData) ? posResData : []
    console.log('Points of sale loaded:', pointsOfSale.value);
  } catch (err) {
    error.value = 'Erreur lors du chargement des données.'
    console.error(err)
  } finally {
    loading.value = false
  }
}

const validateForm = () => {
  errors.value = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    point_of_sale_id: ''
  }

  let isValid = true

  if (!user.value.name.trim()) {
    errors.value.name = 'Le nom est requis'
    isValid = false
  }

  if (!user.value.email.trim()) {
    errors.value.email = "L'email est requis"
    isValid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(user.value.email)) {
    errors.value.email = "L'email n'est pas valide"
    isValid = false
  }

  if (user.value.password && user.value.password.length < 6) {
    errors.value.password = 'Le mot de passe doit contenir au moins 6 caractères'
    isValid = false
  }

  if (user.value.password !== user.value.password_confirmation) {
    errors.value.password_confirmation = 'Les mots de passe ne correspondent pas'
    isValid = false
  }

  if (!user.value.role) {
    errors.value.role = 'Le rôle est requis'
    isValid = false
  }

  return isValid
}

const isFormValid = computed(() => {
  return (
    user.value.name.trim() &&
    user.value.email.trim() &&
    user.value.role &&
    user.value.password === user.value.password_confirmation
  )
})

const updateUser = async () => {
  if (!validateForm()) return

  loading.value = true
  error.value = ''
  success.value = ''

  try {
    const updateData = {
      id: user.value.id,
      name: user.value.name,
      email: user.value.email,
      point_of_sale_ids: user.value.point_of_sale_ids,
      role: user.value.role
    }

    if (user.value.password) {
      updateData.password = user.value.password
      updateData.password_confirmation = user.value.password_confirmation
    }

    await userService.update(updateData)

    success.value = 'Utilisateur mis à jour avec succès !'

    setTimeout(() => {
      router.push({ name: 'dashboard-users' })
    }, 1500)
  } catch (err) {
    error.value = err.response?.data?.message || "Erreur lors de la mise à jour de l'utilisateur."
    console.error(err)
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  user.value = { ...originalUser.value, password: '', password_confirmation: '' }
  errors.value = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    point_of_sale_id: ''
  }
  error.value = ''
  success.value = ''
}

onMounted(loadUser)
</script>

<style scoped>
</style>
