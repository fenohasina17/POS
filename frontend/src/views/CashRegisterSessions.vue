<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Caisse</p>
        <h1 class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          <font-awesome-icon icon="fa-solid fa-cash-register" class="text-indigo-500" />
          Sessions caisse
        </h1>
        <p class="mt-2 text-sm text-slate-500">
          Gérez les caisses rattachées au point de vente de l'utilisateur connecté.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
          @click="refreshData"
          :disabled="loading"
        >
          <font-awesome-icon icon="fa-solid fa-rotate" :class="{ 'animate-spin': loading }" />
          Actualiser
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
          @click="showCreateForm = !showCreateForm"
          :disabled="!userPointOfSaleId"
        >
          <font-awesome-icon icon="fa-solid fa-plus" />
          {{ showCreateForm ? 'Fermer' : 'Nouvelle caisse' }}
        </button>
      </div>
    </header>

    <div
      v-if="errorMessage"
      class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-600"
    >
      <div class="flex items-center gap-2">
        <font-awesome-icon icon="fa-solid fa-triangle-exclamation" />
        <span>{{ errorMessage }}</span>
      </div>
      <button
        type="button"
        class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-600 transition hover:bg-rose-100"
        @click="errorMessage = ''"
      >
        Fermer
      </button>
    </div>

    <div
      v-if="successMessage"
      class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-600"
    >
      <div class="flex items-center gap-2">
        <font-awesome-icon icon="fa-solid fa-circle-check" />
        <span>{{ successMessage }}</span>
      </div>
      <button
        type="button"
        class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-600 transition hover:bg-emerald-100"
        @click="successMessage = ''"
      >
        Fermer
      </button>
    </div>

    <section
      v-if="showCreateForm"
      class="rounded-3xl border border-slate-200 bg-white shadow-sm"
    >
      <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="text-base font-semibold text-slate-800">Ajouter une caisse</h2>
        <p class="mt-1 text-sm text-slate-500">
          La caisse sera automatiquement rattachée au point de vente {{ userPointOfSaleName }}.
        </p>
      </div>

      <form class="grid gap-6 px-6 py-6 sm:grid-cols-[minmax(0,1fr)_auto]" @submit.prevent="createRegister">
        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-600">Nom de la caisse</label>
          <input
            v-model.trim="form.name"
            type="text"
            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            :class="{ 'border-rose-300': formError }"
            placeholder="Ex. Caisse principale"
            required
          />
          <p v-if="formError" class="text-xs font-semibold text-rose-500">{{ formError }}</p>
        </div>

        <div class="flex items-end justify-end gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600"
            @click="resetForm"
          >
            <font-awesome-icon icon="fa-solid fa-xmark" />
            Annuler
          </button>
          <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="saving || !form.name || !userPointOfSaleId"
          >
            <font-awesome-icon icon="fa-solid fa-plus" :class="{ 'animate-spin': saving }" />
            {{ saving ? 'Ajout...' : 'Ajouter' }}
          </button>
        </div>
      </form>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-6 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold text-slate-800">Caisses du point de vente</h2>
            <p class="mt-1 text-sm text-slate-500">{{ userPointOfSaleName }}</p>
          </div>
          <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">
            {{ registers.length }} caisse{{ registers.length > 1 ? 's' : '' }}
          </span>
        </div>
      </div>

      <div
        v-if="loading"
        class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center text-sm text-slate-500"
      >
        <span class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-500"></span>
        <div>
          <p class="font-semibold text-slate-700">Chargement des caisses…</p>
          <p class="text-xs text-slate-400">Veuillez patienter pendant la récupération des données.</p>
        </div>
      </div>

      <div
        v-else-if="!userPointOfSaleId"
        class="px-6 py-16 text-center text-sm text-slate-500"
      >
        Aucun point de vente n'est rattaché à cet utilisateur.
      </div>

      <div
        v-else-if="registers.length === 0"
        class="px-6 py-16 text-center text-sm text-slate-500"
      >
        Aucune caisse enregistrée pour ce point de vente.
      </div>

      <ul v-else class="divide-y divide-slate-100">
        <li
          v-for="register in registers"
          :key="register.id"
          class="flex flex-wrap items-center justify-between gap-4 px-6 py-4"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
              <font-awesome-icon icon="fa-solid fa-cash-register" />
            </div>
            <div>
              <p class="font-semibold text-slate-900">{{ register.name }}</p>
              <p class="text-xs text-slate-400">ID caisse : {{ register.id }}</p>
            </div>
          </div>

          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-2xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
            @click="deleteRegister(register)"
            :disabled="saving"
          >
            <font-awesome-icon icon="fa-solid fa-trash" />
            Supprimer
          </button>
        </li>
      </ul>
    </section>
  </div>
</template>

<script>
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

export default {
  name: 'CashRegisterSessions',
  components: {
    FontAwesomeIcon
  },
  data() {
    return {
      loading: false,
      saving: false,
      showCreateForm: false,
      registers: [],
      userProfile: null,
      form: {
        name: ''
      },
      formError: '',
      errorMessage: '',
      successMessage: ''
    }
  },
  computed: {
    currentUser() {
      if (this.userProfile && typeof this.userProfile === 'object') {
        return this.userProfile
      }

      try {
        return JSON.parse(localStorage.getItem('user') || '{}')
      } catch (error) {
        return {}
      }
    },

    userPointOfSaleId() {
      const pointOfSaleId = Number(this.currentUser?.point_of_sale_id)
      return Number.isFinite(pointOfSaleId) && pointOfSaleId > 0 ? pointOfSaleId : null
    },

    userPointOfSaleName() {
      return this.currentUser?.point_of_sale_name || 'Point de vente non défini'
    }
  },
  async mounted() {
    await this.loadCurrentUserProfile()
    await this.fetchRegisters()
  },
  methods: {
    getAuthHeaders() {
      const token = localStorage.getItem('token')
      return {
        Authorization: `Bearer ${token}`
      }
    },

    resolveRegisterPointOfSaleId(register) {
      const pointOfSaleId = Number(
        register?.point_of_sale_id ??
        register?.pointOfSaleId ??
        register?.point_of_sale?.id ??
        register?.pointOfSale?.id ??
        null
      )

      return Number.isFinite(pointOfSaleId) && pointOfSaleId > 0 ? pointOfSaleId : null
    },

    getFirstValidationError(error) {
      const responseData = error?.response?.data || {}
      const errorBag = responseData.errors || responseData.details || {}

      for (const value of Object.values(errorBag)) {
        if (Array.isArray(value) && value.length > 0) {
          return value[0]
        }
        if (typeof value === 'string' && value.trim()) {
          return value
        }
      }

      return responseData.message || ''
    },

    async loadCurrentUserProfile() {
      try {
        const { data } = await axios.get(`${API_BASE_URL}/me`, {
          headers: this.getAuthHeaders()
        })

        const profile = data?.data || data || null
        if (profile && typeof profile === 'object') {
          this.userProfile = {
            ...this.currentUser,
            ...profile
          }
          localStorage.setItem('user', JSON.stringify(this.userProfile))
        }
      } catch (error) {
        console.error('Erreur lors du chargement du profil utilisateur:', error.response?.data || error.message)
      }
    },

    async fetchRegisters() {
      this.loading = true
      this.errorMessage = ''

      try {
        const { data } = await axios.get(`${API_BASE_URL}/cash-registers`, {
          headers: this.getAuthHeaders()
        })

        const allRegisters = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
        this.registers = this.userPointOfSaleId
          ? allRegisters.filter((register) => this.resolveRegisterPointOfSaleId(register) === this.userPointOfSaleId)
          : []
      } catch (error) {
        console.error('Erreur lors du chargement des caisses:', error)
        this.errorMessage = error.response?.data?.message || 'Impossible de charger les caisses.'
        this.registers = []
      } finally {
        this.loading = false
      }
    },

    resetForm() {
      this.showCreateForm = false
      this.form.name = ''
      this.formError = ''
    },

    async createRegister() {
      this.formError = ''
      this.errorMessage = ''
      this.successMessage = ''

      if (!this.userPointOfSaleId) {
        this.formError = 'Aucun point de vente associe a cet utilisateur.'
        return
      }

      if (!this.form.name.trim()) {
        this.formError = 'Le nom de la caisse est requis.'
        return
      }

      this.saving = true

      try {
        const payload = {
          name: this.form.name.trim()
        }

        if (this.userPointOfSaleId) {
          payload.point_of_sale_id = this.userPointOfSaleId
        }

        const { data } = await axios.post(`${API_BASE_URL}/cash-registers`, payload, {
          headers: this.getAuthHeaders()
        })

        const createdRegister = data?.data || data
        if (createdRegister) {
          this.registers.unshift(createdRegister)
        }

        this.successMessage = 'Caisse ajoutee avec succes.'
        this.resetForm()
        await this.fetchRegisters()
      } catch (error) {
        console.error('Erreur lors de la creation de la caisse:', error)
        this.formError = this.getFirstValidationError(error) || 'Impossible d ajouter la caisse.'
      } finally {
        this.saving = false
      }
    },

    async deleteRegister(register) {
      if (!register) return

      const confirmed = window.confirm(`Supprimer la caisse "${register.name}" ?`)
      if (!confirmed) return

      this.saving = true
      this.errorMessage = ''
      this.successMessage = ''

      try {
        await axios.delete(`${API_BASE_URL}/cash-registers/${register.id}`, {
          headers: this.getAuthHeaders()
        })

        this.registers = this.registers.filter((item) => item.id !== register.id)
        this.successMessage = 'Caisse supprimee avec succes.'
      } catch (error) {
        console.error('Erreur lors de la suppression de la caisse:', error)
        this.errorMessage = error.response?.data?.message || 'Impossible de supprimer la caisse.'
      } finally {
        this.saving = false
      }
    },

    async refreshData() {
      await this.fetchRegisters()
    }
  }
}
</script>
