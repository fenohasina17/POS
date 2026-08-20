<template>
  <div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-black text-slate-900">Utilisateurs</h1>
        <p class="text-xs text-slate-400">Comptes autorisés à accéder au dashboard Central</p>
      </div>
      <button @click="openCreate" class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nouvel utilisateur
      </button>
    </div>

    <!-- Liste -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 text-left">
            <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Nom</th>
            <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Email</th>
            <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Rôle</th>
            <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Créé le</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50 transition">
            <td class="px-5 py-3 font-semibold text-slate-900">
              <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-black">
                  {{ initials(u.name) }}
                </span>
                {{ u.name }}
                <span v-if="u.id === authStore.user?.id" class="text-[10px] text-slate-400">(vous)</span>
              </div>
            </td>
            <td class="px-5 py-3 text-slate-500">{{ u.email }}</td>
            <td class="px-5 py-3">
              <span :class="u.role === 'admin' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600'"
                    class="inline-block rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide">
                {{ u.role === 'admin' ? 'Administrateur' : 'Lecteur' }}
              </span>
            </td>
            <td class="px-5 py-3 text-slate-400 text-xs">{{ fmtDate(u.created_at) }}</td>
            <td class="px-5 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button @click="openEdit(u)" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-indigo-300 hover:text-indigo-600 transition">
                  Modifier
                </button>
                <button v-if="u.id !== authStore.user?.id" @click="confirmDelete(u)"
                        class="rounded-lg border border-red-100 px-3 py-1 text-xs font-semibold text-red-500 hover:bg-red-50 transition">
                  Supprimer
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!users.length">
            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">Aucun utilisateur</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal créer / modifier -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-base font-black text-slate-900">{{ editing ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur' }}</h2>
          <button @click="modal = false" class="text-slate-400 hover:text-slate-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <form @submit.prevent="save" class="space-y-4 px-6 py-5">
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Nom</label>
            <input v-model="form.name" required type="text" placeholder="Prénom Nom"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Email</label>
            <input v-model="form.email" required type="email" placeholder="email@entreprise.mg"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">
              Mot de passe {{ editing ? '(laisser vide pour ne pas changer)' : '' }}
            </label>
            <input v-model="form.password" :required="!editing" type="password" placeholder="Min. 8 caractères"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Rôle</label>
            <select v-model="form.role" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none">
              <option value="viewer">Lecteur — consultation uniquement</option>
              <option value="admin">Administrateur — accès complet</option>
            </select>
          </div>
          <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">{{ error }}</p>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="modal = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
              Annuler
            </button>
            <button type="submit" :disabled="saving" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition">
              {{ saving ? 'Enregistrement…' : (editing ? 'Enregistrer' : 'Créer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Confirmation suppression -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <div class="w-full max-w-sm rounded-2xl bg-white shadow-xl p-6 space-y-4">
        <h2 class="text-base font-black text-slate-900">Supprimer l'utilisateur ?</h2>
        <p class="text-sm text-slate-500">
          <strong>{{ deleteTarget.name }}</strong> ({{ deleteTarget.email }}) perdra immédiatement l'accès au dashboard.
        </p>
        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">{{ error }}</p>
        <div class="flex justify-end gap-2">
          <button @click="deleteTarget = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">Annuler</button>
          <button @click="doDelete" :disabled="saving" class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-600 disabled:opacity-50 transition">
            {{ saving ? 'Suppression…' : 'Supprimer' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/utils/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const users       = ref([])
const modal       = ref(false)
const editing     = ref(null)
const saving      = ref(false)
const error       = ref('')
const deleteTarget = ref(null)

const form = ref({ name: '', email: '', password: '', role: 'viewer' })

const initials = (name) => name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
const fmtDate  = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—'

async function load() {
  const res = await api.get('/users')
  users.value = res.data
}

function openCreate() {
  editing.value = null
  form.value = { name: '', email: '', password: '', role: 'viewer' }
  error.value = ''
  modal.value = true
}

function openEdit(u) {
  editing.value = u
  form.value = { name: u.name, email: u.email, password: '', role: u.role }
  error.value = ''
  modal.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    const payload = { ...form.value }
    if (editing.value) {
      if (!payload.password) delete payload.password
      await api.put(`/users/${editing.value.id}`, payload)
    } else {
      await api.post('/users', payload)
    }
    modal.value = false
    await load()
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value = errs ? Object.values(errs).flat().join(' ') : (e.response?.data?.message ?? 'Erreur')
  } finally {
    saving.value = false
  }
}

function confirmDelete(u) {
  deleteTarget.value = u
  error.value = ''
}

async function doDelete() {
  saving.value = true
  error.value = ''
  try {
    await api.delete(`/users/${deleteTarget.value.id}`)
    deleteTarget.value = null
    await load()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erreur'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
