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
          Ajoutez un nouveau compte et rattachez-le au point de vente approprié.
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
            <p class="text-xs text-slate-400">Le mot de passe doit contenir au moins 8 caracteres.</p>
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
            <p class="text-xs text-slate-400">Utilisez la meme valeur pour confirmer le mot de passe.</p>
            <p v-if="errors.password_confirmation" class="text-xs font-semibold text-rose-500">
              {{ errors.password_confirmation }}
            </p>
          </div>

          <div class="space-y-2 sm:col-span-2">
            <label class="text-sm font-medium text-slate-600">Point de vente</label>
            <select
              v-model="user.point_of_sale_id"
              required
              :disabled="loadingPointsOfSale"
              class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-50"
              :class="{ 'border-rose-300': errors.point_of_sale_id }"
            >
              <option value="">Selectionner un point de vente</option>
              <option v-for="pos in pointsOfSale" :key="pos.id" :value="pos.id">
                {{ pos.name }}
              </option>
            </select>
            <p v-if="loadingPointsOfSale" class="text-xs text-slate-400">Chargement des points de vente...</p>
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

<script>
import userService from '@/services/userService'
import pointOfSaleService from '@/services/pointOfSaleService'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

export default {
  name: 'UserCreate',
  components: {
    FontAwesomeIcon
  },
  data() {
    return {
      user: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        point_of_sale_id: null
      },
      errors: {},
      isCreating: false,
      pointsOfSale: [],
      loadingPointsOfSale: false
    }
  },
  computed: {
    isFormValid() {
      return this.user.email.trim() &&
        this.user.password.length >= 8 &&
        this.user.password === this.user.password_confirmation &&
        this.user.point_of_sale_id !== null
    }
  },
  async mounted() {
    await this.fetchPointsOfSale()
  },
  methods: {
    async fetchPointsOfSale() {
      this.loadingPointsOfSale = true
      try {
        const response = await pointOfSaleService.getAll()
        this.pointsOfSale = response.data || response
      } catch (error) {
        console.error('Erreur lors du chargement des points de vente:', error)
        this.errors.point_of_sale_id = 'Impossible de charger les points de vente'
      } finally {
        this.loadingPointsOfSale = false
      }
    },
    async createUser() {
      this.errors = {}

      // Validation name
      if (!this.user.name || this.user.name.trim().length === 0) {
        this.errors.name = 'Le nom est requis'
        return
      }

      // Validation email
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      if (!emailRegex.test(this.user.email)) {
        this.errors.email = 'Veuillez entrer une adresse email valide'
        return
      }

      // Validation mot de passe
      if (this.user.password.length < 8) {
        this.errors.password = 'Le mot de passe doit contenir au moins 8 caractères'
        return
      }

      // Validation confirmation
      if (this.user.password !== this.user.password_confirmation) {
        this.errors.password_confirmation = 'Les mots de passe ne correspondent pas'
        return
      }

      try {
        this.isCreating = true
        await userService.create(this.user)
        this.$router.push('/users')
      } catch (error) {
        console.error('Erreur lors de la création de l\'utilisateur:', error)
        if (error.response?.data?.errors) {
          this.errors = error.response.data.errors
        } else {
          this.errors.general = 'Erreur lors de la création de l\'utilisateur. Veuillez réessayer.'
        }
      } finally {
        this.isCreating = false
      }
    }
  }
}
</script>
