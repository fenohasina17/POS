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
              <h1 class="mt-3 text-3xl font-semibold leading-tight sm:text-4xl">Laissez parler la performance de vos pizzerias</h1>
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
            <p class="mt-1 leading-relaxed">Vos raccourcis préférés sont accessibles dès la connexion via le tableau de bord. Pensez à associer votre machine à la caisse avant d’ouvrir une session.</p>
          </div>
        </div>

        <div class="flex flex-col justify-center px-6 py-10 sm:px-10 lg:px-12">
          <div class="mb-10">
            <p class="text-sm font-semibold uppercase tracking-[0.4em] text-indigo-500">Connexion</p>
            <h2 class="mt-3 text-3xl font-semibold text-slate-900">Accéder à votre espace</h2>
            <p class="mt-2 text-sm text-slate-500">Identifiez-vous pour retrouver vos sessions, vos ventes et les actions rapides d’IGP POS.</p>
          </div>

          <form class="space-y-5" @submit.prevent="login">
            <div class="space-y-2">
              <label for="email" class="text-sm font-medium text-slate-600">Identifiant</label>
              <input
                id="email"
                ref="emailInput"
                v-model="email"
                type="email"
                placeholder="prenom.nom@entreprise.com"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                @focus="showKeyboard('email')"
              />
            </div>

            <div class="space-y-2">
              <label for="password" class="text-sm font-medium text-slate-600">Mot de passe</label>
              <input
                id="password"
                ref="passwordInput"
                type="password"
                v-model="password"
                placeholder="••••••••"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                @focus="showKeyboard('password')"
              />
            </div>

            <button
              type="submit"
              class="inline-flex w-full items-center justify-center rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-300"
            >
              <i class="fas fa-right-to-bracket mr-2"></i>
              Se connecter
            </button>

            <p v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-600">
              {{ error }}
            </p>
          </form>

          <div class="mt-10 space-y-2 text-sm">
            <p class="text-slate-500">
              Pas encore de compte ?
              <router-link to="/register" class="font-semibold text-indigo-600 hover:text-indigo-700">Créer un compte</router-link>
            </p>
            <p class="text-xs text-slate-400">En vous connectant, vous acceptez les conditions d’utilisation et la politique de confidentialité d’IGP POS.</p>
          </div>
        </div>
      </div>
    </div>
    <Keyboard
      v-if="keyboardVisible"
      :initial-position="keyboardPosition"
      @key-pressed="handleKeyPress"
      @close="closeKeyboard"
    />
    <PosSelectionModal 
      :is-open="isPosModalOpen" 
      :points-of-sale="userPointsOfSale" 
      @select="handlePosSelection" 
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { storage } from '@/utils/storage'
import Keyboard from '../components/tools/Keyboard.vue'
import PosSelectionModal from '../components/PosSelectionModal.vue'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

defineOptions({ name: 'LoginPage' })

const email = ref('')
const password = ref('')
const error = ref('')
const keyboardVisible = ref(false)
const activeField = ref(null)
const user = ref(null)
const keyboardPosition = ref(null)
const isPosModalOpen = ref(false)
const userPointsOfSale = ref([])

const emailInput = ref(null)
const passwordInput = ref(null)

const login = async () => {
  error.value = '';

  if (!email.value || !password.value) {
    error.value = "Veuillez entrer votre identifiant et mot de passe.";
    return;
  }

  try {
    const response = await axios.post(`${API_BASE_URL}/login`, {
      email: email.value,
      password: password.value
    });

    if (response.data.token && response.data.user) {
      storage.setAuth(response.data.token, response.data.user);
      user.value = response.data.user;
      
      const posList = response.data.user.points_of_sale || [];

      if (posList.length === 0) {
        error.value = "Aucun point de vente associé à votre compte.";
      } else if (posList.length === 1) {
        storage.setActivePos(posList[0]);
        window.location.href = '/dashboard';
      } else {
        userPointsOfSale.value = posList;
        isPosModalOpen.value = true;
      }
    } else {
      error.value = "Réponse du serveur invalide";
    }
  } catch (err) {
    if (err.response?.status === 401) {
      error.value = "Identifiants incorrects";
    } else {
      error.value = "Erreur de connexion au serveur";
    }
  }
};

const handlePosSelection = (pos) => {
  storage.setActivePos(pos);
  isPosModalOpen.value = false;
  window.location.href = '/dashboard';
};

// Fonction pour récupérer l'utilisateur au chargement de l'app
const initUser = () => {
  const auth = storage.getAuth();
  if (auth.user) {
    user.value = auth.user;
  }
};

function showKeyboard(field) {
  activeField.value = field
  keyboardVisible.value = true
}

function handleKeyPress(key) {
  if (key === 'BACKSPACE') {
    if (activeField.value === 'email') email.value = email.value.slice(0, -1)
    if (activeField.value === 'password') password.value = password.value.slice(0, -1)
  } else {
    if (activeField.value === 'email') email.value += key
    if (activeField.value === 'password') password.value += key
  }
}

function closeKeyboard() {
  keyboardVisible.value = false
  activeField.value = null
  keyboardPosition.value = null
}

function updateKeyboardPosition() {
  if (!keyboardVisible.value) return
  const target = activeField.value === 'password' ? passwordInput.value : emailInput.value
  const viewportHeight = window.innerHeight
  const viewportWidth = window.innerWidth
  const keyboardHeight = 280
  const keyboardWidth = 640
  const margin = 16

  if (target) {
    const rect = target.getBoundingClientRect()
    let top = rect.bottom + margin
    if (viewportHeight - rect.bottom < keyboardHeight + margin) {
      top = viewportHeight - keyboardHeight - margin
    }

    let left = rect.left + rect.width / 2 - keyboardWidth / 2

    if (left < margin) left = margin
    if (left + keyboardWidth + margin > viewportWidth) {
      left = viewportWidth - keyboardWidth - margin
    }

    keyboardPosition.value = { top: Math.max(margin, top), left: Math.max(margin, left) }
  } else {
    keyboardPosition.value = {
      top: viewportHeight - keyboardHeight - margin,
      left: Math.max(margin, viewportWidth / 2 - keyboardWidth / 2)
    }
  }
}

const handleResize = () => {
  updateKeyboardPosition()
}

onMounted(() => {
  initUser();
  window.addEventListener('resize', handleResize, { passive: true })

  // Vérifier si une session auth valide existe
  const auth = storage.getAuth();
  if (auth.token) {
    window.location.href = '/dashboard'
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
})
</script>

<style scoped>
</style>
