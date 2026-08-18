<template>
  <div class="min-h-screen bg-gray-900 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-white">POS Central</h1>
        <p class="text-gray-500 text-sm mt-1">Tableau de bord siège</p>
      </div>

      <form @submit.prevent="submit" class="bg-gray-800 rounded-2xl p-8 space-y-5">
        <div>
          <label class="block text-xs text-gray-400 mb-1.5">Email</label>
          <input v-model="form.email" type="email" required autocomplete="email"
            class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-gray-200
                   focus:outline-none focus:border-emerald-500 transition-colors" />
        </div>

        <div>
          <label class="block text-xs text-gray-400 mb-1.5">Mot de passe</label>
          <input v-model="form.password" type="password" required autocomplete="current-password"
            class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-gray-200
                   focus:outline-none focus:border-emerald-500 transition-colors" />
        </div>

        <p v-if="error" class="text-red-400 text-xs bg-red-900/30 border border-red-800 rounded-lg px-3 py-2">
          {{ error }}
        </p>

        <button type="submit" :disabled="loading"
          class="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed
                 text-white font-medium py-2.5 rounded-lg transition-colors text-sm">
          {{ loading ? 'Connexion...' : 'Se connecter' }}
        </button>
      </form>
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

async function submit() {
  loading.value = true
  error.value   = ''
  try {
    await auth.login(form.value.email, form.value.password)
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = e.response?.data?.message
      ?? e.response?.data?.errors?.email?.[0]
      ?? 'Erreur de connexion.'
  } finally {
    loading.value = false
  }
}
</script>
