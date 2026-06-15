<template>
  <div class="min-h-screen bg-slate-100">
    <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
      <div class="grid w-full grid-cols-1 gap-8 rounded-3xl border border-slate-200 bg-white/90 shadow-2xl backdrop-blur lg:grid-cols-2">
        <div class="flex flex-col justify-between rounded-3xl bg-gradient-to-br from-indigo-700 via-indigo-600 to-slate-900 p-8 text-white lg:p-12">
          <div class="flex items-center gap-3">
            <img src="../assets/logoigp.jpg" alt="Logo International Gastronomy Pizza" class="h-14 w-auto rounded-xl bg-white/10 p-1 shadow" />
            <div>
              <p class="text-sm uppercase tracking-[0.3em] text-white/70">International Gastronomy Pizza</p>
              <p class="text-xl font-semibold">Système de caisse intégré</p>
            </div>
          </div>
          <div class="mt-16 space-y-6">
            <div>
              <p class="text-sm uppercase tracking-[0.2em] text-white/60">Bienvenue</p>
              <h1 class="mt-3 text-3xl font-semibold leading-tight sm:text-4xl">Rejoignez l'équipe IGP POS</h1>
            </div>
            <ul class="space-y-3 text-sm text-white/80">
              <li class="flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-full bg-white/10">
                  <i class="fas fa-bolt text-white"></i>
                </span>
                Commandes directes et en salle synchronisées
              </li>
              <li class="flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-full bg-white/10">
                  <i class="fas fa-lock text-white"></i>
                </span>
                Sessions caisse sécurisées
              </li>
              <li class="flex items-center gap-3">
                <span class="flex size-9 items-center justify-center rounded-full bg-white/10">
                  <i class="fas fa-chart-line text-white"></i>
                </span>
                Indicateurs de performance en temps réel
              </li>
            </ul>
          </div>
          <div class="mt-12 rounded-2xl border border-white/20 bg-white/10 p-6 text-sm text-white/70">
            <p class="font-medium text-white">Astuce</p>
            <p class="mt-1 leading-relaxed">Une fois votre compte créé, un administrateur devra vous attribuer un rôle et un point de vente avant que vous puissiez utiliser la caisse.</p>
          </div>
        </div>

        <div class="flex flex-col justify-center px-6 py-10 sm:px-10 lg:px-12">
          <div class="mb-10">
            <p class="text-sm font-semibold uppercase tracking-[0.4em] text-indigo-500">Inscription</p>
            <h2 class="mt-3 text-3xl font-semibold text-slate-900">Créer votre compte</h2>
            <p class="mt-2 text-sm text-slate-500">Renseignez vos informations pour rejoindre l'espace IGP POS.</p>
          </div>

          <form class="space-y-5" @submit.prevent="register">
            <div class="space-y-2">
              <label for="name" class="text-sm font-medium text-slate-600">Nom complet</label>
              <input
                id="name"
                v-model="name"
                type="text"
                placeholder="Prénom Nom"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              />
            </div>

            <div class="space-y-2">
              <label for="email" class="text-sm font-medium text-slate-600">Identifiant</label>
              <input
                id="email"
                v-model="email"
                type="email"
                placeholder="prenom.nom@entreprise.com"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              />
            </div>

            <div class="space-y-2">
              <label for="password" class="text-sm font-medium text-slate-600">Mot de passe</label>
              <input
                id="password"
                v-model="password"
                type="password"
                placeholder="••••••••"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              />
            </div>

            <div class="space-y-2">
              <label for="password_confirmation" class="text-sm font-medium text-slate-600">Confirmer le mot de passe</label>
              <input
                id="password_confirmation"
                v-model="passwordConfirmation"
                type="password"
                placeholder="••••••••"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              />
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="inline-flex w-full items-center justify-center rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <i class="fas fa-user-plus mr-2"></i>
              {{ loading ? 'Création en cours...' : 'Créer mon compte' }}
            </button>

            <p v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-600">
              {{ error }}
            </p>

            <p v-if="success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-600">
              {{ success }}
            </p>
          </form>

          <div class="mt-10 space-y-2 text-sm">
            <p class="text-slate-500">
              Déjà un compte ?
              <router-link to="/" class="font-semibold text-indigo-600 hover:text-indigo-700">Se connecter</router-link>
            </p>
            <p class="text-xs text-slate-400">
              En créant un compte, vous acceptez les conditions d'utilisation et la politique de confidentialité d'IGP POS.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { storage } from '@/utils/storage'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

defineOptions({ name: 'RegisterPage' })

const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref('')
const success = ref('')
const loading = ref(false)

const register = async () => {
  error.value = ''
  success.value = ''

  if (!name.value || !email.value || !password.value || !passwordConfirmation.value) {
    error.value = 'Veuillez remplir tous les champs.'
    return
  }

  if (password.value.length < 8) {
    error.value = 'Le mot de passe doit contenir au moins 8 caractères.'
    return
  }

  if (password.value !== passwordConfirmation.value) {
    error.value = 'Les mots de passe ne correspondent pas.'
    return
  }

  loading.value = true

  try {
    const response = await axios.post(`${API_BASE_URL}/register`, {
      name: name.value,
      email: email.value,
      password: password.value,
    })

    if (response.data.token && response.data.user) {
      storage.setAuth(response.data.token, response.data.user)
      success.value = 'Compte créé avec succès. Redirection...'
      setTimeout(() => {
        router.replace('/dashboard')
      }, 800)
    } else {
      error.value = 'Réponse du serveur invalide'
    }
  } catch (err) {
    if (err.response?.status === 422) {
      const errors = err.response.data?.errors || {}
      error.value = Object.values(errors).flat().join(' ') || 'Données invalides.'
    } else {
      error.value = 'Erreur lors de la création du compte.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
</style>
