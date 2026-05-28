import { ref } from 'vue'
import axios from 'axios'
import { API_BASE_URL } from '@/utils/api'
import { dataCacheService } from '@/services/dataCacheService'
import { useAuth } from '@/composables/useAuth'

export function useCategories() {
  const categories = ref([])
  const products = ref([])
  const filteredProducts = ref([])
  const activeCategory = ref(null)
  const categoryPrinterTypes = ref({})
  const productCatalog = ref({})

  const { activePos } = useAuth()

  const loadCategories = async (forceRefresh = false) => {
    console.log('loadCategories : démarrage de l\'exécution')

    try {
      const token = localStorage.getItem('token')
      console.log('loadCategories : jeton récupéré :', token ? 'Présent' : 'Absent')
      if (!token) {
        console.log('loadCategories : aucun jeton trouvé, arrêt anticipé')
        return
      }

      if (!activePos.value?.id) {
        console.log('loadCategories : aucun point de vente actif, arrêt anticipé')
        return
      }

      const rawCategories = await dataCacheService.getCategories(activePos.value.id, token, forceRefresh)
      console.log('loadCategories : catégories reçues :', rawCategories.length, 'catégories')
      const categoryPrinterMap = { ...categoryPrinterTypes.value }
      const aggregatedProducts = []

      console.log('loadCategories : traitement des catégories pour déterminer les types d\'imprimante')
      rawCategories.forEach(category => {
        if (!category || !category.id) return
        const printerTypeId = resolveCategoryPrinterTypeId(category)
        if (printerTypeId) {
          categoryPrinterMap[category.id] = printerTypeId
        }
      })
      console.log('loadCategories : correspondance catégorie/imprimante après traitement :', categoryPrinterMap)

      console.log('loadCategories : normalisation des produits')
      rawCategories.forEach(category => {
        const fallbackPrinterTypeId = resolveCategoryPrinterTypeId(category) ?? (category?.id ? categoryPrinterMap[category.id] : null)
        if (Array.isArray(category?.products)) {
          category.products = category.products.map(product => {
            const normalized = normalizeProduct(product, category, fallbackPrinterTypeId)
            aggregatedProducts.push(normalized)
            return normalized
          })
        }
      })
      console.log('loadCategories : aggregatedProducts count', aggregatedProducts.length)


      console.log('loadCategories : categories set', categories.value.map(c=>c.id))
      console.log('loadCategories : products set', products.value.length)

      categoryPrinterTypes.value = categoryPrinterMap
      products.value = aggregatedProducts
      filteredProducts.value = aggregatedProducts
      console.log('loadCategories : filteredProducts set', filteredProducts.value.length)


      console.log('loadCategories : exécution terminée avec succès')
    } catch (error) {
      console.error('loadCategories : une erreur est survenue :', error)
      console.error('loadCategories : réponse d\'erreur :', error.response?.data)
      console.error('loadCategories : statut d\'erreur :', error.response?.status)
      console.error('loadCategories : message d\'erreur :', error.message)
    }
  }

const loadProducts = (category = null) => {
  console.log('loadProducts : called with category', category);
  activeCategory.value = category;

  let productsToProcess = [];
  if (category && category.id) {
    productsToProcess = products.value.filter(p => p.category_id === category.id);
  } else {
    productsToProcess = products.value;
  }

  const fallbackPrinterTypeId = resolveCategoryPrinterTypeId(category);

  filteredProducts.value = productsToProcess.map(product => {
    const categoryId = product.category_id ?? category?.id ?? null;
    const printerTypeId = product.printer_type_id ?? resolveProductPrinterTypeId(product, category) ?? fallbackPrinterTypeId;

    const price = Number(
      product.price ??
      (Array.isArray(product.pricing) && product.pricing.length ? parseFloat(product.pricing[0].price) : 0)
    ) || 0;

    const stock = resolveProductStock(product);

    const normalized = {
      ...product,
      category_id: categoryId,
      printer_type_id: printerTypeId,
      price,
      stock,
    };

    normalized.isAvailable = checkProductAvailability(normalized);

    registerProduct(normalized, {
      category,
      category_id: categoryId,
      printer_type_id: printerTypeId,
      price,
    });

    console.log('loadProducts : normalized product', normalized);
    return normalized;
  });
};

  const resolveCategoryPrinterTypeId = (category) => {
    if (!category) return null
    if (category.printer_type_id) return category.printer_type_id
    if (typeof category.printer_type === 'number') return category.printer_type
    if (category.printer_type && typeof category.printer_type === 'object') {
      return category.printer_type.id ?? category.printer_type.printer_type_id ?? null
    }
    if (category.id && categoryPrinterTypes.value[category.id]) {
      return categoryPrinterTypes.value[category.id]
    }
    return null
  }

  const resolveProductPrinterTypeId = (product, fallbackCategory = null) => {
    if (!product) return null
    if (product.printer_type_id) return product.printer_type_id
    if (typeof product.printer_type === 'number') return product.printer_type
    if (product.printer_type && typeof product.printer_type === 'object') {
      return product.printer_type.id ?? product.printer_type.printer_type_id ?? null
    }
    const resolvedCategory = product.category || fallbackCategory || (product.category_id ? { id: product.category_id } : null)
    if (resolvedCategory && resolvedCategory.printer_type_id) {
      return resolvedCategory.printer_type_id
    }
    if (resolvedCategory && resolvedCategory.printer_type && typeof resolvedCategory.printer_type === 'object') {
      return resolvedCategory.printer_type.id ?? resolvedCategory.printer_type.printer_type_id ?? null
    }
    if (resolvedCategory && resolvedCategory.id && categoryPrinterTypes.value[resolvedCategory.id]) {
      return categoryPrinterTypes.value[resolvedCategory.id]
    }
    if (product.category_id && categoryPrinterTypes.value[product.category_id]) {
      return categoryPrinterTypes.value[product.category_id]
    }
    return null
  }

  const registerProduct = (product, overrides = {}) => {
    if (!product || !product.id) return

    const category = overrides.category || product.category || null
    const categoryId = overrides.category_id ?? product.category_id ?? category?.id ?? null
    const resolvedCategoryPrinterType = overrides.printer_type_id ?? resolveProductPrinterTypeId(product, category) ?? (categoryId ? categoryPrinterTypes.value[categoryId] : null)
    const price = Number(overrides.price ?? product.price ?? (product.pricing?.[0]?.price ? parseFloat(product.pricing[0].price) : 0)) || 0

    if (categoryId && resolvedCategoryPrinterType && categoryPrinterTypes.value[categoryId] !== resolvedCategoryPrinterType) {
      categoryPrinterTypes.value = {
        ...categoryPrinterTypes.value,
        [categoryId]: resolvedCategoryPrinterType
      }
    }

    productCatalog.value = {
      ...productCatalog.value,
      [product.id]: {
        id: product.id,
        name: product.name,
        category_id: categoryId,
        printer_type_id: resolvedCategoryPrinterType,
        price,
        product: { ...product }
      }
    }
  }

  const normalizeProduct = (product, category, fallbackPrinterTypeId = null) => {
    if (!product) return null

    const normalizedCategoryId = product.category_id ?? category?.id ?? null
    const normalizedPrice = Number(
      product.price ??
      (Array.isArray(product.pricing) && product.pricing.length ? parseFloat(product.pricing[0].price) : 0)
    ) || 0
    const normalizedPrinterTypeId = resolveProductPrinterTypeId(product, category) ?? fallbackPrinterTypeId ?? (normalizedCategoryId ? categoryPrinterTypes.value[normalizedCategoryId] : null)

    const baseProduct = {
      ...product,
      category_id: normalizedCategoryId,
      price: normalizedPrice,
      printer_type_id: normalizedPrinterTypeId
    }

    const stock = resolveProductStock(baseProduct)
    const normalizedProduct = {
      ...baseProduct,
      stock: stock,
      category_name: category?.name ?? product?.category_name ?? product?.category?.name ?? '—',
    }

    normalizedProduct.isAvailable = checkProductAvailability(normalizedProduct)

    registerProduct(normalizedProduct, {
      category,
      category_id: normalizedCategoryId,
      printer_type_id: normalizedPrinterTypeId,
      price: normalizedPrice
    })

    return normalizedProduct
  }

  const resolveProductStock = (product) => {
    if (!product) return null

    const candidates = [
      product.stock,
      product.available_stock,
      product.quantity,
      product.available_quantity,
      product.inventory?.quantity,
      product.inventory?.stock
    ]

    for (const value of candidates) {
      if (typeof value === 'number' && Number.isFinite(value)) {
        return value
      }

      if (typeof value === 'string') {
        const trimmed = value.trim()
        if (trimmed !== '') {
          const parsed = Number(trimmed)
          if (Number.isFinite(parsed)) {
            return parsed
          }
        }
      }
    }

    return null
  }

  const checkProductAvailability = (product) => {
    if (!product) {
      return false
    }

    if (Object.prototype.hasOwnProperty.call(product, 'is_available')) {
      return Boolean(product.is_available)
    }

    if (Object.prototype.hasOwnProperty.call(product, 'isAvailable')) {
      return Boolean(product.isAvailable)
    }

    const stock = resolveProductStock(product)
    const hasStock = stock === null || stock === undefined ? true : Number(stock) > 0

    const rawPrice = product.price ?? (Array.isArray(product.pricing) && product.pricing.length ? product.pricing[0].price : 0)
    const numericPrice = Number(rawPrice)
    const hasValidPrice = !Number.isNaN(numericPrice) && numericPrice > 0

    return hasStock && hasValidPrice
  }

  return {
    categories,
    products,
    filteredProducts,
    activeCategory,
    categoryPrinterTypes,
    productCatalog,
    loadCategories,
    loadProducts,
    resolveCategoryPrinterTypeId,
    resolveProductPrinterTypeId,
    registerProduct,
    normalizeProduct,
    resolveProductStock,
    checkProductAvailability
  }
}
