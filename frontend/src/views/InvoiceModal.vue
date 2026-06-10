<template>
  <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 overflow-hidden">
    <!-- Backdrop avec flou premium -->
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md transition-opacity" @click="closeModal"></div>

    <div class="relative w-full max-w-3xl rounded-[2rem] bg-white shadow-2xl shadow-slate-900/20 transition-all flex flex-col max-h-[95vh]">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
        <div>
          <h2 class="text-xl font-black text-slate-800 tracking-tight">Reçu de vente</h2>
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Détails de la transaction</p>
        </div>
        <button
          @click="closeModal"
          class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-50 text-slate-400 transition-all hover:bg-rose-50 hover:text-rose-500 active:scale-95"
        >
          <FontAwesomeIcon icon="fa-solid fa-times" class="text-xs" />
        </button>
      </div>

      <!-- Contenu en deux colonnes -->
      <div class="flex-1 min-h-0 grid grid-cols-2">

        <!-- Colonne Gauche : Résumé & Paiement -->
        <div class="p-6 border-r border-slate-100 overflow-y-auto scrollbar-hide flex flex-col gap-4">
          <!-- En-tête Ticket -->
          <div class="flex flex-col items-center text-center p-4 rounded-3xl bg-indigo-50 border border-indigo-100 shadow-sm">
            <div class="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 shadow-inner">
              <FontAwesomeIcon icon="fa-solid fa-receipt" class="text-xl" />
            </div>
            <p class="text-base font-black text-indigo-900 leading-tight">Ticket N°{{ invoiceNumber }}</p>
            <p class="text-[10px] font-bold text-indigo-500 mt-1 uppercase tracking-widest">{{ currentDateTime }}</p>
          </div>

          <!-- Infos Client & Paiement -->
          <div class="grid grid-cols-1 gap-3">
            <div class="flex items-center justify-between rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Client</p>
              <p class="text-xs font-black text-slate-800">{{ clientName || 'Client' }}</p>
            </div>
          </div>

          <!-- Règlement détaillé : Tous les paiements sans regroupement -->
          <div v-if="payments?.length" class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Détails Règlement</p>
            <div class="space-y-3">
              <div v-for="(payment, index) in payments" :key="index" class="flex items-center justify-between text-xs border-b border-slate-50 pb-2 last:border-0 last:pb-0">
                <div class="flex items-center gap-2">
                  <FontAwesomeIcon :icon="getPaymentIcon(payment.payment_name)" class="text-slate-400" />
                  <div>
                    <p class="font-bold text-slate-700">{{ payment.payment_method_name }}</p>
                    <p v-if="payment.reference" class="text-[9px] text-slate-400">Réf: {{ payment.reference }}</p>
                  </div>
                </div>
                <p class="font-black text-slate-900">{{ formatPrice(payment.amount) }}</p>
              </div>
            </div>
          </div>

          <!-- Section Monnaie / Reste -->
          <div v-if="totalPaymentsAmount !== finalTotal"
               class="rounded-2xl p-4 border shadow-sm"
               :class="totalPaymentsAmount > finalTotal ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100'"
          >
            <div class="flex items-center justify-between text-sm">
              <p class="font-black uppercase tracking-widest" :class="totalPaymentsAmount > finalTotal ? 'text-emerald-700' : 'text-rose-700'">
                {{ totalPaymentsAmount > finalTotal ? 'Rendu client' : 'Reste à payer' }}
              </p>
              <p class="font-black" :class="totalPaymentsAmount > finalTotal ? 'text-emerald-800' : 'text-rose-800'">
                {{ formatPrice(Math.abs(totalPaymentsAmount - finalTotal)) }}
              </p>
            </div>
          </div>

          <!-- Totaux Finaux -->
          <div class="mt-auto rounded-3xl bg-slate-50 border border-slate-100 p-6 shadow-sm">
            <div class="flex justify-between items-center mb-2">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total payé</p>
              <div class="px-2 py-0.5 rounded-lg bg-indigo-50 text-[8px] font-black text-indigo-600 uppercase tracking-widest">
                {{ totalPaymentsAmount >= finalTotal ? 'PAYÉ' : 'PARTIEL' }}
              </div>
            </div>
            <h2 class="text-3xl font-black text-slate-900 tracking-tight">{{ formatPrice(finalTotal) }}</h2>
          </div>
        </div>

        <!-- Colonne Droite : Liste des Produits -->
        <div class="bg-slate-50/30 p-6 overflow-y-auto scrollbar-hide flex flex-col min-h-0">
          <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
            Articles
            <span class="h-px flex-1 bg-slate-100"></span>
            <span class="bg-slate-100 px-2 py-0.5 rounded text-[9px]">{{ items.length }}</span>
          </h4>

          <div class="space-y-2 flex-1 overflow-y-auto pr-1">
            <div v-for="(item, index) in groupedItems" :key="index" class="flex items-center justify-between rounded-xl bg-white p-3 border border-slate-100 shadow-sm transition-all hover:border-indigo-100">
              <div class="flex-1 min-w-0 pr-2">
                <p class="text-xs font-black text-slate-950 truncate leading-tight">{{ item.name }}</p>
                <p class="text-sm font-medium text-slate-400 mt-0.5">
                  {{ item.quantity }} x {{ formatPrice(item.price) }}
                </p>
              </div>
              <p class="text-sm font-black text-indigo-600">
                {{ formatPrice(item.price * item.quantity) }}
              </p>
            </div>
          </div>

          <!-- Message de remerciement discret -->
          <div class="mt-4 text-center opacity-30">
            <p class="text-[8px] font-black uppercase tracking-[0.3em]">Gastronomie Pizza</p>
          </div>
        </div>
      </div>

      <!-- Actions Footer -->
      <div class="p-6 grid grid-cols-2 gap-3 border-t border-slate-50 bg-white rounded-b-[2rem]">
        <button
          @click="printInvoicePDF"
          class="flex items-center justify-center gap-2 rounded-xl bg-white border border-slate-200 py-3 text-[10px] font-black text-slate-600 transition-all hover:bg-slate-50 active:scale-95"
        >
          <FontAwesomeIcon icon="fa-solid fa-print" class="text-xs" />
          IMPRIMER
        </button>
        <button
          @click="closeModal"
          class="flex items-center justify-center gap-2 rounded-xl bg-indigo-600 py-3 text-[10px] font-black text-white shadow-lg shadow-indigo-100 transition-all hover:bg-indigo-700 active:scale-95"
        >
          <FontAwesomeIcon icon="fa-solid fa-check-circle" class="text-xs" />
          TERMINER
        </button>
      </div>

      <!-- Notification Toast -->
      <div v-if="notification.show" class="fixed bottom-4 right-4 z-[200] animate-slide-up">
        <div :class="[
          'rounded-xl px-4 py-3 shadow-lg flex items-center gap-3',
          notification.type === 'success' ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'
        ]">
          <FontAwesomeIcon :icon="notification.type === 'success' ? 'fa-solid fa-check-circle' : 'fa-solid fa-exclamation-circle'" />
          <p class="text-sm font-medium">{{ notification.message }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { printingService } from '@/services/printing/PrintingService'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
  faTimes, faPrint, faMoneyBillWave, faMobileAlt, faCreditCard,
  faFileInvoice, faReceipt, faCheck, faCheckCircle, faFilePdf,
  faExclamationCircle
} from '@fortawesome/free-solid-svg-icons'

library.add(faTimes, faPrint, faMoneyBillWave, faMobileAlt, faCreditCard, faFileInvoice, faReceipt, faCheck, faCheckCircle, faFilePdf, faExclamationCircle)

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  items: { type: Array, default: () => [] },
  total: { type: Number, default: 0 },
  clientName: { type: String, default: 'Client' },
  invoiceNumber: { type: String, default: '' },
  paymentMethod: { type: String, default: '' },
  payments: { type: Array, default: () => [] },
  discountPercentage: { type: Number, default: 0 },
  tableName: { type: String, default: 'Vente Directe' }
})
const emit = defineEmits(['close-modal', 'clear-cart'])

// État local
const savePDF = ref(false)
const silentPrint = ref(true)
const notification = ref({ show: false, message: '', type: 'success' })

// Notification helper
const showNotification = (message, type = 'success') => {
  notification.value = { show: true, message, type }
  setTimeout(() => {
    notification.value.show = false
  }, 3000)
}

// Icône selon le mode de paiement
const getPaymentIcon = (methodName) => {
  if (!methodName) return 'fa-solid fa-money-bill-wave'
  const name = methodName.toLowerCase()
  if (name.includes('espèce') || name.includes('cash')) return 'fa-solid fa-money-bill-wave'
  if (name.includes('orange') || name.includes('airtel') || name.includes('wave') || name.includes('mtn')) return 'fa-solid fa-mobile-alt'
  if (name.includes('carte')) return 'fa-solid fa-credit-card'
  if (name.includes('chèque')) return 'fa-solid fa-file-invoice'
  return 'fa-solid fa-money-bill-wave'
}

const groupedItems = computed(() => {
  const map = new Map()
  props.items.forEach(item => {
    const key = `${item.name}-${item.price}`
    if (map.has(key)) {
      map.get(key).quantity += Number(item.quantity)
    } else {
      map.set(key, { ...item, quantity: Number(item.quantity) })
    }
  })
  return Array.from(map.values())
})

const totalPaymentsAmount = computed(() => {
  return (props.payments || []).reduce((sum, p) => sum + Number(p.amount || 0), 0)
})

const discountAmount = computed(() => (props.total * props.discountPercentage) / 100)
const finalTotal = computed(() => props.total - discountAmount.value)

const currentDateTime = computed(() => new Date().toLocaleString('fr-FR', {
  year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
}))

const formatPrice = (price) => {
  const value = Number.parseFloat(price)
  if (!Number.isFinite(value)) return '—'
  return `${value.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} Ar`
}

const closeModal = () => emit('close-modal')

// Préparer les données d'impression
const prepareInvoiceData = () => {
  const invoiceData = {
    companyName: 'INTERNATIONAL GASTRONOMY PIZZA',
    address: 'Antananarivo, Madagascar',
    phone: '034 00 000 00',
    number: props.invoiceNumber || 'REC-' + Date.now(),
    date: currentDateTime.value,
    items: props.items.map(item => ({
      name: item.name || item.product?.name || 'Article',
      price: Number(item.price) || 0,
      quantity: Number(item.quantity) || 1
    })),
    subtotal: props.total,
    discount: discountAmount.value,
    total: finalTotal.value,
    client: props.clientName || 'Client',
    payments: props.payments?.map(p => ({
      method: p.method || 'Espèces',
      amount: Number(p.amount) || 0,
      reference: p.reference || null
    })) || [{ method: 'Espèces', amount: finalTotal.value }]
  }
  return invoiceData
}

// Impression directe (ancienne méthode)
const printInvoiceDirect = async () => {
  try {
    const invoiceData = prepareInvoiceData()
    await printingService.printInvoice(invoiceData)

    const tableInfo = { name: props.tableName || 'Vente Directe', ticketNumber: invoiceData.number }
    const orderItems = props.items.map(item => ({
      ...item,
      name: item.name || item.product?.name || 'Article',
      quantity: Number(item.quantity) || 1,
      price: Number(item.price) || 0
    }))
    await printingService.printOrder(tableInfo, orderItems)

    showNotification('Impression directe réussie', 'success')
  } catch (error) {
    console.error('Échec de l\'impression directe:', error)
    showNotification('Erreur impression directe: ' + error.message, 'error')
  }
}
const printInvoicePDF = async () => {
  try {
    console.log('[DEBUG_PRINT] --- DÉBUT DU TRAITEMENT DE LA COMMANDE ---');

    // Préparation des données globales de la facture
    const invoiceData = prepareInvoiceData();

    // ----------------------------------------------------------------
    // 1. LA FACTURE CLIENT : Sort TOUJOURS sur l'imprimante 'receipt'
    // ----------------------------------------------------------------
    console.log('[DEBUG_PRINT] Étape 1 : Envoi de la facture globale à la caisse (receipt)...');

    if (silentPrint.value) {
      // On force explicitement 'receipt' pour l'imprimante de la caisse principale
      const result = await printingService.printInvoicePDF({ ...invoiceData, printerName: 'receipt' });
      if (result.success) showNotification('Facture imprimée à la caisse', 'success');
      else showNotification('Erreur facture caisse: ' + result.message, 'error');
    } else {
      const result = await printingService.generateAndPrintInvoicePDF({ ...invoiceData, printerName: 'receipt' }, savePDF.value);
      if (result.success) showNotification('Facture générée avec succès', 'success');
    }

    // ----------------------------------------------------------------
    // 2. LES BONS DE CUISINE : Ventilés selon la catégorie du produit
    // ----------------------------------------------------------------
    if (props.items.length > 0) {
      console.log('[DEBUG_PRINT] Étape 2 : Ventilation des articles pour les bons de préparation...');

      const tableInfo = {
        name: props.tableName || 'Vente Directe',
        ticketNumber: invoiceData.number
      };

      // Regroupement dynamique basé STRICTEMENT sur item.printer (ex: 'bar', 'kitchen')
      const itemsByPrinter = props.items.reduce((acc, item) => {
        // Correction ici : On prend la racine exacte confirmée par vos logs, sinon repli sur 'receipt'
        const targetPrinter = item.printer || 'receipt';

        if (!acc[targetPrinter]) {
          acc[targetPrinter] = [];
        }

        acc[targetPrinter].push({
          name: item.name || item.product?.name || 'Article',
          quantity: Number(item.quantity) || 1,
          price: Number(item.price) || 0
        });

        return acc;
      }, {});

      console.log('[DEBUG_PRINT] Groupes d’impression détectés :', Object.keys(itemsByPrinter));

      // Envoi de chaque bon de préparation vers son imprimante dédiée
      for (const [printerName, printerItems] of Object.entries(itemsByPrinter)) {
        console.log(`[DEBUG_PRINT] Envoi du bon de préparation vers -> "${printerName}" (${printerItems.length} articles)`);

        // On passe le printerName issu du produit à notre service
        const orderResult = await printingService.printOrderPDF(tableInfo, printerItems, printerName);

        if (!orderResult.success) {
          console.warn(`[DEBUG_PRINT] Échec d'impression préparation sur "${printerName}":`, orderResult.message);
        } else {
          console.log(`[DEBUG_PRINT] Succès d'envoi du bon vers "${printerName}"`);
        }
      }
    }

    console.log('[DEBUG_PRINT] --- FIN DU TRAITEMENT DE LA COMMANDE ---');
  } catch (error) {
    console.error('[DEBUG_PRINT] Erreur générale lors du clic imprimer:', error);
    showNotification('Erreur système: ' + error.message, 'error');
  }
};

// Récupérer les imprimantes disponibles
const loadPrinters = async () => {
  try {
    const printers = await printingService.getAvailablePrinters()
    console.log('Imprimantes disponibles:', printers)
  } catch (error) {
    console.error('Erreur chargement imprimantes:', error)
  }
}

// Charger les imprimantes au montage
if (props.isOpen) {
  loadPrinters()
}

</script>

<style scoped>
@keyframes slide-up {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-slide-up {
  animation: slide-up 0.3s ease-out;
}

@media print {
  .fixed { position: relative !important; }
  .fixed button { display: none !important; }
  .overflow-y-auto { overflow: visible !important; max-height: none !important; }
  .bg-black { background: none !important; }
  .shadow-xl { box-shadow: none !important; }
}

/* Scrollbar personnalisée */
.scrollbar-hide::-webkit-scrollbar {
  width: 4px;
}

.scrollbar-hide::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.scrollbar-hide::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}
</style>
