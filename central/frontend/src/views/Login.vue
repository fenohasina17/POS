<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
    <div class="w-full max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white/90 shadow-2xl lg:grid lg:grid-cols-2">

      <!-- Colonne gauche — branding -->
      <div class="hidden flex-col justify-between bg-gradient-to-br from-indigo-700 via-indigo-600 to-slate-900 p-10 text-white lg:flex">
        <div class="flex items-center gap-3">
          <img src="../assets/logoigp.jpg" alt="IGP" class="h-10 w-auto rounded-xl object-cover" />
          <div>
            <p class="text-sm font-bold tracking-wide">IGP Central</p>
            <p class="text-[10px] text-white/60">Supervision siège</p>
          </div>
        </div>

        <div class="space-y-6">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/50">Tableau de bord</p>
            <h1 class="mt-2 text-3xl font-bold leading-tight">
              Supervisez l'ensemble<br/>de vos pizzerias
            </h1>
            <p class="mt-3 text-sm text-white/70">
              Suivez en temps réel les performances de chaque terminal, les ventes et les alertes.
            </p>
          </div>

          <ul class="space-y-3">
            <li v-for="feature in features" :key="feature" class="flex items-center gap-3 text-sm text-white/80">
              <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/10">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
              </span>
              {{ feature }}
            </li>
          </ul>
        </div>

        <p class="text-[10px] text-white/30">International Gastronomy Pizza · {{ new Date().getFullYear() }}</p>
      </div>

      <!-- Colonne droite — formulaire -->
      <div class="flex flex-col justify-center p-8 sm:p-12">
        <div class="mb-8">
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-500">Connexion</p>
          <h2 class="mt-1 text-2xl font-bold text-slate-900">Accéder au tableau de bord</h2>
          <p class="mt-1 text-sm text-slate-500">Identifiez-vous avec votre compte administrateur.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="mb-1.5 block text-xs font-semibold text-slate-600">Email</label>
            <input
              v-model="form.email"
              type="email"
              required
              autocomplete="email"
              placeholder="admin@central.local"
              class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-xs font-semibold text-slate-600">Mot de passe</label>
            <input
              v-model="form.password"
              type="password"
              required
              autocomplete="current-password"
              class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
            />
          </div>

          <div v-if="error" class="flex items-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
            <svg class="h-4 w-4 shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-xs text-rose-600">{{ error }}</p>
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="w-full rounded-2xl bg-indigo-600 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {{ loading ? 'Connexion en cours…' : 'Se connecter' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()

const form    = ref({ email: '', password: '' })
const loading = ref(false)
const error   = ref('')

const features = [
  'Terminaux en temps réel',
  'Chiffre d\'affaires consolidé',
  'Alertes & synchronisation',
]

async function submit() {
  loading.value = true
  error.value   = ''
  try {
    await auth.login(form.value.email, form.value.password)
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = e.response?.data?.message
      ?? e.response?.data?.errors?.email?.[0]
      ?? 'Identifiants incorrects.'
  } finally {
    loading.value = false
  }
}
</script>
