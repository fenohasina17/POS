<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Administration</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-user-pen" class="text-indigo-500" />
          Modifier l’utilisateur
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Ajustez les informations du compte et rattachez-le aux rôles et points de vente appropriés.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <router-link
          :to="{ name: 'dashboard-users' }"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
        >
          <font-awesome-icon icon="fa-solid fa-arrow-left" />
          Retour
        </router-link>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
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
      class="flex flex-col items-center justify-center gap-3 rounded-3xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-500 shadow-sm"
    >
      <span class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-500"></span>
      <div>
        <p class="font-semibold text-slate-700">Chargement de l’utilisateur…</p>
        <p class="text-xs text-slate-400">Nous récupérons les informations du profil.</p>
      </div>
    </div>

    <div v-else class="space-y-6">
      <div
        v-if="error"
        class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-600"
      >
        <div class="flex items-center gap-2">
          <font-awesome-icon icon="fa-solid fa-triangle-exclamation" />
          <span>{{ error }}</span>
        </div>
        <button
          type="button"
          class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-600 transition hover:bg-rose-100"
          @click="error = ''"
        >
          Fermer
        </button>
      </div>

      <div
        v-if="success"
        class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-600"
      >
        <div class="flex items-center gap-2">
          <font-awesome-icon icon="fa-solid fa-circle-check" />
          <span>{{ success }}</span>
        </div>
        <button
          type="button"
          class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-600 transition hover:bg-emerald-100"
          @click="success = ''"
        >
          Fermer
        </button>
      </div>

      <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-6 px-6 py-6 sm:grid-cols-2">
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Nom d’utilisateur</label>
            <input
              v-model="user.name"
              type="text"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              placeholder="Entrez le nom de l’utilisateur"
              :class="{ 'border-rose-300': errors.name }"
            />
            <p v-if="errors.name" class="text-xs font-semibold text-rose-500">{{ errors.name }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Email</label>
            <input
              v-model="user.email"
              type="email"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              placeholder="prenom.nom@entreprise.com"
              :class="{ 'border-rose-300': errors.email }"
            />
            <p v-if="errors.email" class="text-xs font-semibold text-rose-500">{{ errors.email }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Nouveau mot de passe</label>
            <input
              v-model="user.password"
              type="password"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              placeholder="Laisser vide pour ne pas changer"
              :class="{ 'border-rose-300': errors.password }"
            />
            <p v-if="errors.password" class="text-xs font-semibold text-rose-500">{{ errors.password }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Confirmer le mot de passe</label>
            <input
              v-model="user.password_confirmation"
              type="password"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              placeholder="Confirmez le mot de passe"
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

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-600">Point de vente</label>
            <select
              v-model="user.point_of_sale_id"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-rose-300': errors.point_of_sale_id }"
            >
              <option value="">Sélectionner un point de vente</option>
              <option v-for="pos in pointsOfSale" :key="pos.id" :value="pos.id">
                {{ pos.name }}
              </option>
            </select>
            <p v-if="errors.point_of_sale_id" class="text-xs font-semibold text-rose-500">
              {{ errors.point_of_sale_id }}
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
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

const user = ref({
  id: null,
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: '',
  point_of_sale_id: null
})

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

const loadUser = async () => {
  loading.value = true
  error.value = ''

  try {
    const userId = route.params.id
    const [userResponse, rolesResponse, userRolesResponse] = await Promise.all([
      userService.getUser(userId),
      roleService.getAll(),
      userService.getRoles(userId)
    ])

    user.value = { ...userResponse.data, password: '', password_confirmation: '' }
    originalUser.value = { ...userResponse.data }
    roles.value = rolesResponse.data

    if (Array.isArray(userRolesResponse.data) && userRolesResponse.data.length > 0) {
      user.value.role = userRolesResponse.data[0].name
    }

    const posResponse = await pointOfSaleService.getAll()
    pointsOfSale.value = posResponse.data || posResponse
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
      point_of_sale_id: user.value.point_of_sale_id
    }

    if (user.value.password) {
      updateData.password = user.value.password
      updateData.password_confirmation = user.value.password_confirmation
    }

    await userService.update(updateData)

    if (user.value.role) {
      await userService.assignRole(user.value.id, user.value.role)
    }

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
