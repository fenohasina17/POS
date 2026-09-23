<template>
  <div class="space-y-6">

    <!-- En-tête -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-black text-slate-900">Catalogue produits</h1>
        <p class="text-xs text-slate-400">Référence unique diffusée vers toutes les caisses POS</p>
      </div>
      <div class="flex items-center gap-2">
        <input ref="fileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="onFileSelected" />
        <button @click="fileInput.click()" :disabled="importing"
                class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:border-indigo-300 hover:text-indigo-600 transition disabled:opacity-50">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
          {{ importing ? 'Import en cours…' : 'Importer un fichier Excel' }}
        </button>
        <button @click="openCreate" class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Nouveau produit
        </button>
      </div>
    </div>

    <p v-if="importResult" class="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700">
      Import terminé — {{ importResult.created }} produit{{ importResult.created > 1 ? 's' : '' }} créé{{ importResult.created > 1 ? 's' : '' }},
      {{ importResult.updated }} mis à jour.
    </p>
    <p v-if="importError" class="rounded-lg bg-red-50 px-4 py-2.5 text-sm text-red-600">{{ importError }}</p>

    <!-- Recherche -->
    <div class="relative max-w-xs">
      <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
      <input v-model="search" type="text" placeholder="Rechercher un produit…"
             class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm focus:border-indigo-400 focus:outline-none" />
    </div>

    <!-- Liste -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 text-left">
            <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Référence</th>
            <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Nom</th>
            <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Catégorie</th>
            <th class="px-5 py-3 text-[10px] font-black uppercase tracking-wide text-slate-400">Taille</th>
            <th class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-wide text-slate-400">Prix</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr v-for="p in filteredProducts" :key="p.id" class="hover:bg-slate-50 transition">
            <td class="px-5 py-3 font-mono text-xs text-slate-400">{{ p.ref }}</td>
            <td class="px-5 py-3 font-semibold text-slate-900">{{ p.name }}</td>
            <td class="px-5 py-3">
              <span class="inline-block rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-600">
                {{ p.category?.name ?? '—' }}
              </span>
            </td>
            <td class="px-5 py-3 text-slate-500">{{ p.size ?? '—' }}</td>
            <td class="px-5 py-3 text-right font-mono text-slate-700">{{ fmt(p.price) }}</td>
            <td class="px-5 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button @click="openEdit(p)" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-indigo-300 hover:text-indigo-600 transition">
                  Modifier
                </button>
                <button @click="confirmDelete(p)" class="rounded-lg border border-red-100 px-3 py-1 text-xs font-semibold text-red-500 hover:bg-red-50 transition">
                  Supprimer
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!loading && !filteredProducts.length">
            <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">
              {{ search ? 'Aucun produit ne correspond à la recherche' : 'Aucun produit — importez un fichier Excel ou créez-en un' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal créer / modifier -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-base font-black text-slate-900">{{ editing ? 'Modifier le produit' : 'Nouveau produit' }}</h2>
          <button @click="modal = false" class="text-slate-400 hover:text-slate-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <form @submit.prevent="save" class="space-y-4 px-6 py-5">
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Référence</label>
            <input v-model="form.ref" required maxlength="20" type="text" placeholder="Ex. PZ-001"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Nom</label>
            <input v-model="form.name" required type="text" placeholder="Nom du produit"
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Catégorie</label>
            <select v-model="form.category_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none">
              <option value="" disabled>— Choisir —</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              <option value="__new__">+ Nouvelle catégorie…</option>
            </select>
            <input v-if="form.category_id === '__new__'" v-model="newCategoryName" required type="text" placeholder="Nom de la nouvelle catégorie"
                   class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Prix de vente (Ar)</label>
              <input v-model.number="form.price" required type="number" min="0" step="1"
                     class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Taille</label>
              <input v-model="form.size" type="text" placeholder="Optionnel"
                     class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
            </div>
          </div>
          <p class="text-[10px] text-slate-400">La taille est une métadonnée d'affichage Central — elle n'est jamais transmise aux caisses POS.</p>
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
        <h2 class="text-base font-black text-slate-900">Supprimer le produit ?</h2>
        <p class="text-sm text-slate-500">
          <strong>{{ deleteTarget.name }}</strong> ({{ deleteTarget.ref }}) ne sera plus diffusé aux caisses lors des prochaines synchronisations.
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
import { ref, computed, onMounted } from 'vue'
import api from '@/utils/api'
import { fmt } from '@/utils/formatters'

const products   = ref([])
const categories  = ref([])
const loading     = ref(true)
const search      = ref('')

const modal       = ref(false)
const editing     = ref(null)
const saving      = ref(false)
const error       = ref('')
const deleteTarget = ref(null)

const importing    = ref(false)
const importResult = ref(null)
const importError  = ref('')
const fileInput    = ref(null)

const newCategoryName = ref('')
const form = ref({ ref: '', name: '', category_id: '', price: 0, size: '' })

const filteredProducts = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return products.value
  return products.value.filter(p =>
    p.name.toLowerCase().includes(q) ||
    p.ref.toLowerCase().includes(q) ||
    (p.category?.name ?? '').toLowerCase().includes(q)
  )
})

async function load() {
  loading.value = true
  try {
    const [productsRes, categoriesRes] = await Promise.all([
      api.get('/catalog-products'),
      api.get('/categories'),
    ])
    products.value   = productsRes.data
    categories.value = categoriesRes.data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { ref: '', name: '', category_id: '', price: 0, size: '' }
  newCategoryName.value = ''
  error.value = ''
  modal.value = true
}

function openEdit(p) {
  editing.value = p
  form.value = { ref: p.ref, name: p.name, category_id: p.category_id, price: Number(p.price), size: p.size ?? '' }
  newCategoryName.value = ''
  error.value = ''
  modal.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    let categoryId = form.value.category_id
    if (categoryId === '__new__') {
      const res = await api.post('/categories', { name: newCategoryName.value })
      categoryId = res.data.id
      categories.value.push(res.data)
    }

    const payload = { ...form.value, category_id: categoryId, size: form.value.size || null }

    if (editing.value) {
      await api.put(`/catalog-products/${editing.value.id}`, payload)
    } else {
      await api.post('/catalog-products', payload)
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

function confirmDelete(p) {
  deleteTarget.value = p
  error.value = ''
}

async function doDelete() {
  saving.value = true
  error.value = ''
  try {
    await api.delete(`/catalog-products/${deleteTarget.value.id}`)
    deleteTarget.value = null
    await load()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erreur'
  } finally {
    saving.value = false
  }
}

async function onFileSelected(e) {
  const file = e.target.files[0]
  if (!file) return

  importing.value = true
  importResult.value = null
  importError.value = ''
  try {
    const formData = new FormData()
    formData.append('file', file)
    const res = await api.post('/catalog-products/import', formData)
    importResult.value = res.data
    await load()
  } catch (err) {
    importError.value = err.response?.data?.message ?? 'Échec de l\'import du fichier.'
  } finally {
    importing.value = false
    e.target.value = ''
  }
}

onMounted(load)
</script>
