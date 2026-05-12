<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col">

      <!-- Header -->
      <div class="px-6 py-5 border-b flex items-center justify-between bg-slate-50 rounded-t-3xl">
        <h2 class="text-2xl font-bold text-slate-900">
          Modifier Vente #{{ sale?.sale_number || sale?.ticket_number || 'N/A' }}
        </h2>
        <button @click="close" class="text-3xl text-slate-400 hover:text-red-500">×</button>
      </div>

      <div class="flex-1 overflow-auto p-6 space-y-6">
        <!-- Ajout de produit (Admin) -->
        <div v-if="isAdmin" class="bg-slate-50 border border-slate-200 rounded-2xl p-5">
          <h3 class="font-semibold text-slate-700 mb-4">Ajouter un produit</h3>
          <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <!-- Sélecteur de catégorie -->
            <div class="md:col-span-3">
              <label class="block text-sm font-medium text-slate-600 mb-1.5">Catégorie</label>
              <select v-model="selectedCategoryId" class="w-full rounded-2xl border border-slate-300 py-3 px-4 focus:border-indigo-500">
                <option :value="null">-- Toutes les catégories --</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
            </div>
            <!-- Sélecteur de produit (filtré par catégorie) -->
            <div class="md:col-span-5">
              <label class="block text-sm font-medium text-slate-600 mb-1.5">Produit</label>
              <select v-model="selectedProductId" class="w-full rounded-2xl border border-slate-300 py-3 px-4 focus:border-indigo-500">
                <option value="">-- Sélectionner un produit --</option>
                <option v-for="p in filteredProductsByCategory" :key="p.id" :value="p.id">
                  {{ p.name }} — {{ p.price != null ? formatPrice(p.price) : 'Prix non défini' }}
                </option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-600 mb-1.5">Quantité</label>
              <input
                type="number"
                v-model.number="newProductQuantity"
                min="1"
                class="w-full rounded-2xl border border-slate-300 py-3 px-4 focus:border-indigo-500"
              />
            </div>
            <div class="md:col-span-2">
              <button
                @click="addSelectedProduct"
                :disabled="!selectedProductId || newProductQuantity < 1"
                class="w-full h-12 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-medium disabled:opacity-50"
              >
                Ajouter
              </button>
            </div>
          </div>
        </div>

        <!-- Liste des produits -->
        <div>
          <h3 class="font-semibold text-slate-700 mb-3">Produits dans la vente</h3>
          <div class="border rounded-2xl overflow-hidden">
            <table class="w-full">
              <thead class="bg-slate-50">
                <tr>
                  <th class="text-left p-4">Produit</th>
                  <th class="text-center p-4">Quantité</th>
                  <th class="text-right p-4">Prix Unitaire</th>
                  <th class="text-right p-4">Total</th>
                  <th class="w-12"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, index) in orderLines" :key="index" class="border-t hover:bg-slate-50">
                  <td class="p-4">{{ line.product?.name || line.name || 'Produit inconnu' }}</td>
                  <td class="p-4 text-center font-medium">{{ line.quantity }}</td>
                  <td class="p-4 text-right">{{ formatPrice(line.price) }}</td>
                  <td class="p-4 text-right font-semibold">{{ formatPrice(line.total) }}</td>
                  <td class="p-4 text-center">
                    <button
                      @click="removeLine(index)"
                      class="text-red-500 hover:text-red-700 text-xl font-bold"
                    >
                      ✕
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="p-6 border-t bg-slate-50 rounded-b-3xl flex justify-between items-center">
        <div class="text-2xl font-bold text-emerald-600">
          Total : {{ formatPrice(totalAmount) }}
        </div>
        <div class="flex gap-3">
          <button @click="close" class="px-6 py-3 text-slate-600 hover:bg-slate-100 rounded-2xl">
            Annuler
          </button>
          <button @click="saveChanges" class="px-8 py-3 bg-indigo-600 text-white rounded-2xl hover:bg-indigo-700">
            Enregistrer les modifications
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { dataCacheService } from '@/services/dataCacheService'
import { storage } from '@/utils/storage'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'

const props = defineProps({
  sale: { type: Object, required: true }
})

const emit = defineEmits(['save', 'close'])

const { isAdmin } = useAuth()

const isOpen = ref(true)
const categories = ref([])
const products = ref([])
const selectedCategoryId = ref(null)
const selectedProductId = ref('')
const newProductQuantity = ref(1)
const orderLines = ref([])

const totalAmount = computed(() =>
  orderLines.value.reduce((sum, line) => sum + Number(line.total || 0), 0)
)

const formatPrice = (price) => {
  return Number(price || 0).toLocaleString('fr-FR') + ' Ar'
}

// Produits filtrés par catégorie
const filteredProductsByCategory = computed(() => {
  if (selectedCategoryId.value === null) return products.value
  return products.value.filter(p => p.category_id === selectedCategoryId.value)
})

// Charger les catégories et produits via dataCacheService (comme dans DirectSale)
const loadCategoriesAndProducts = async () => {
  try {
    const auth = storage.getAuth()
    const user = auth?.user
    if (!user?.point_of_sale_id) {
      console.warn('Aucun point de vente associé')
      return
    }
    const data = await dataCacheService.getCategories(
      user.point_of_sale_id,
      auth.token,
      false
    )
    // Sauvegarde des catégories
    categories.value = data
    // Aplatir tous les produits avec leur category_id et price
    const allProducts = data.flatMap(category =>
      (category.products || []).map(product => ({
        ...product,
        category_id: category.id,
        category_name: category.name,
        price: product.pricing?.[0]?.price ?? null
      }))
    )
    products.value = allProducts
    console.log(`✅ ${products.value.length} produits chargés (depuis categories)`)
  } catch (e) {
    console.error('Erreur chargement catégories/produits', e)
  }
}

// Ajouter un produit sélectionné
const addSelectedProduct = () => {
  if (!selectedProductId.value) return

  const product = filteredProductsByCategory.value.find(p => p.id === Number(selectedProductId.value))
  if (!product) {
    alert("Produit introuvable")
    return
  }

  if (product.price == null) {
    alert(`Le produit "${product.name}" n'a pas de prix défini pour ce point de vente. Impossible de l'ajouter.`)
    return
  }

  orderLines.value.push({
    product_id: product.id,
    product: product,
    quantity: newProductQuantity.value,
    price: product.price,
    total: product.price * newProductQuantity.value
  })

  // Réinitialiser les sélections
  selectedProductId.value = ''
  selectedCategoryId.value = null
  newProductQuantity.value = 1
}

const removeLine = (index) => {
  if (confirm('Supprimer ce produit ?')) {
    orderLines.value.splice(index, 1)
  }
}

const saveChanges = async () => {
  try {
    const payload = {
      orderlines: orderLines.value.map(line => ({
        product_id: line.product_id,
        quantity: line.quantity,
        price: line.price
      }))
    }

    const { data } = await axios.put(
      `${API_BASE_URL}/sales/${props.sale.id}/order-lines`,
      payload,
      { headers: { Authorization: `Bearer ${storage.getAuth()?.token}` } }
    )

    emit('save', data.sale || data)
    alert('✅ Modifications enregistrées avec succès')
    close()
  } catch (error) {
    console.error(error)
    alert('❌ Erreur lors de la sauvegarde')
  }
}

const close = () => emit('close')

onMounted(() => {
  loadCategoriesAndProducts()
  // Initialiser les lignes de commande existantes
  const lines = Array.isArray(props.sale?.orderlines) ? [...props.sale.orderlines] : []
  orderLines.value = lines.map(line => ({
    ...line,
    price: line.price ?? line.unit_price ?? 0,
    total: (line.price ?? line.unit_price ?? 0) * (line.quantity ?? 1)
  }))
})
</script>
