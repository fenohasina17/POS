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
          class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-50 text-slate-400 transition-all hover:bg-danger-50 hover:text-danger-500 active:scale-95"
        >
          <FontAwesomeIcon icon="fa-solid fa-times" class="text-xs" />
        </button>
      </div>

      <!-- Contenu en deux colonnes -->
      <div class="flex-1 min-h-0 grid grid-cols-2">

        <!-- Colonne Gauche : Résumé & Paiement -->
        <div class="p-6 border-r border-slate-100 overflow-y-auto scrollbar-hide flex flex-col gap-4">
          <!-- En-tête Ticket -->
          <div class="flex flex-col items-center text-center p-4 rounded-3xl bg-primary-50 border border-primary-100 shadow-sm">
            <div class="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-primary-600 shadow-inner">
              <FontAwesomeIcon icon="fa-solid fa-receipt" class="text-xl" />
            </div>
            <p class="text-base font-black text-primary-900 leading-tight">Ticket N° {{ ticketNumber }}</p>
            <p class="text-[10px] font-bold text-primary-500 mt-1 uppercase tracking-widest">{{ currentDateTime }}</p>
          </div>

          <!-- Infos Client & Paiement -->
          <div class="grid grid-cols-1 gap-3">
            <div class="ui-card flex items-center justify-between p-4">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Client</p>
              <p class="text-xs font-black text-slate-800">{{ clientName || 'Client' }}</p>
            </div>
          </div>

          <!-- Règlement détaillé : Tous les paiements sans regroupement -->
          <div v-if="payments?.length" class="ui-card p-4">
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
               :class="totalPaymentsAmount > finalTotal ? 'bg-success-50 border-success-100' : 'bg-danger-50 border-danger-100'"
          >
            <div class="flex items-center justify-between text-sm">
              <p class="font-black uppercase tracking-widest" :class="totalPaymentsAmount > finalTotal ? 'text-success-700' : 'text-danger-700'">
                {{ totalPaymentsAmount > finalTotal ? 'Rendu client' : 'Reste à payer' }}
              </p>
              <p class="font-black" :class="totalPaymentsAmount > finalTotal ? 'text-success-800' : 'text-danger-800'">
                {{ formatPrice(Math.abs(totalPaymentsAmount - finalTotal)) }}
              </p>
            </div>
          </div>

          <!-- Totaux Finaux -->
          <div class="mt-auto rounded-3xl bg-slate-50 border border-slate-100 p-6 shadow-sm">
            <div class="flex justify-between items-center mb-2">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total payé</p>
              <div class="px-2 py-0.5 rounded-lg bg-primary-50 text-[8px] font-black text-primary-600 uppercase tracking-widest">
                {{ totalPaymentsAmount >= finalTotal ? 'PAYÉ' : 'PARTIEL' }}
              </div>
            </div>
            <h2 class="text-3xl font-black text-slate-900 tracking-tight">{{ formatPrice(finalTotal) }}</h2>
          </div>
        </div>

        <!-- Colonne Droite : Liste des Produits -->
        <div class="bg-slate-50/30 p-6 overflow-y-auto scrollbar-hide flex flex-col min-h-0">
          <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
            {{ showTicketPreview ? 'Aperçu du ticket' : 'Articles' }}
            <span class="h-px flex-1 bg-slate-100"></span>
            <button
              type="button"
              data-testid="toggle-ticket-preview"
              @click="showTicketPreview = !showTicketPreview"
              class="rounded-lg bg-white border border-slate-200 px-2 py-0.5 text-[9px] font-black text-slate-500 hover:text-primary-600 hover:border-primary-200"
            >
              {{ showTicketPreview ? 'Articles' : 'Aperçu ticket' }}
            </button>
          </h4>

          <!-- Aperçu fidèle du ticket imprimé (même modèle qu'Electron, largeur 80 mm) -->
          <div v-if="showTicketPreview" class="flex-1 overflow-y-auto flex justify-center">
            <iframe
              title="Aperçu du ticket"
              :srcdoc="ticketPreviewHtml"
              class="bg-white shadow-sm border border-slate-200"
              style="width: 80mm; border-radius: 4px;"
              :style="{ height: previewHeight + 'px' }"
              @load="onPreviewLoad"
            ></iframe>
          </div>

          <div v-else class="space-y-2 flex-1 overflow-y-auto pr-1">
            <div v-for="(item, index) in groupedItems" :key="index" class="flex items-center justify-between rounded-xl bg-white p-3 border border-slate-100 shadow-sm transition-all hover:border-primary-100">
              <div class="flex-1 min-w-0 pr-2">
                <p class="text-xs font-black text-slate-950 truncate leading-tight">{{ item.name }}</p>
                <p class="text-sm font-medium text-slate-400 mt-0.5">
                  {{ item.quantity }} x {{ formatPrice(item.price) }}
                </p>
              </div>
              <p class="text-sm font-black text-primary-600">
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
      <div class="p-6 flex justify-center border-t border-slate-50 bg-white rounded-b-[2rem]">
        <button
          @click="printAndClose"
          class="flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-12 py-3 text-[10px] font-black text-white shadow-lg shadow-primary-100 transition-all hover:bg-primary-700 active:scale-95"
        >
          <FontAwesomeIcon icon="fa-solid fa-print" class="text-xs" />
          IMPRIMER LE REÇU
        </button>
      </div>

      <!-- Notification Toast -->
      <div v-if="notification.show" class="fixed bottom-4 right-4 z-[200] animate-slide-up">
        <div :class="[
          'rounded-xl px-4 py-3 shadow-lg flex items-center gap-3',
          notification.type === 'success' ? 'bg-success-500 text-white' : 'bg-danger-500 text-white'
        ]">
          <FontAwesomeIcon :icon="notification.type === 'success' ? 'fa-solid fa-check-circle' : 'fa-solid fa-exclamation-circle'" />
          <p class="text-sm font-medium">{{ notification.message }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { printingService } from '@/services/printing/PrintingService'
import { buildReceiptHTML, formatServiceLabel } from '@/services/printing/receiptTemplate'
import { storage } from '@/utils/storage'
import logoUrl from '@/assets/logo-ticket-nb.png' // Logo noir et blanc pour l'imprimante thermique
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
  tableName: { type: String, default: 'Vente Directe' },
  // Réponse formatée de l'API vente ({ sale, totals, payments }) : alimente le ticket client
  saleInfo: { type: Object, default: null }
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

// ================= Ticket client (modèle « LA GASTRONOMIE PIZZA ») =================
const showTicketPreview = ref(false)
const previewHeight = ref(600)
const logoDataUrl = ref('')

// Le logo est transmis en data URL : la fenêtre d'impression Electron est une page data: isolée
const loadLogo = async () => {
  if (logoDataUrl.value) return logoDataUrl.value
  try {
    const blob = await (await fetch(logoUrl)).blob()
    logoDataUrl.value = await new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => resolve(reader.result)
      reader.onerror = reject
      reader.readAsDataURL(blob)
    })
  } catch (e) {
    console.warn('[TICKET] Logo indisponible :', e)
  }
  return logoDataUrl.value
}

// Numéro de ticket seul (le parent passe parfois « Vente #1001 »)
const ticketNumber = computed(() =>
  props.saleInfo?.sale?.ticket_number ?? String(props.invoiceNumber || '').replace(/^vente\s*#\s*/i, '')
)

const receiptData = computed(() => {
  const sale = props.saleInfo?.sale || {}
  const totals = props.saleInfo?.totals || {}
  const session = storage.getSession() || {}
  const activePos = storage.getActivePos() || {}
  const auth = storage.getAuth()

  let serviceLabel = ''
  if (props.saleInfo?.sale) {
    serviceLabel = formatServiceLabel({
      serviceType: sale.service_type,
      tableNumber: sale.table_id ? sale.table_number : null
    })
  } else if (props.clientName && props.clientName.startsWith('Table')) {
    serviceLabel = props.clientName
  }

  const amountReceived = totals.paid_amount ?? (props.payments?.length ? totalPaymentsAmount.value : null)
  const changeAmount = totals.change_amount ?? (amountReceived !== null ? Math.max(0, amountReceived - finalTotal.value) : null)

  return {
    title: 'LA GASTRONOMIE PIZZA',
    logoSrc: logoDataUrl.value || logoUrl,
    ticketNumber: ticketNumber.value,
    posCode: sale.point_of_sale_name ?? activePos.name ?? '',
    cashierName: sale.cashier_name ?? auth.user?.name ?? '',
    serverName: '', // Pas encore de serveur affecté aux tables : ligne « Serveur » masquée
    cashRegisterName: session.cash_register?.name || localStorage.getItem('cashRegisterMachineName') || '',
    date: sale.date_raw || new Date().toISOString(),
    serviceLabel,
    payments: (props.payments || []).map(p => ({
      method: p.payment_method_name || p.method || p.payment_name || '',
      amount: Number(p.amount) || 0
    })),
    amountReceived: amountReceived !== null && amountReceived !== undefined ? Number(amountReceived) : null,
    changeAmount: changeAmount !== null && changeAmount !== undefined ? Number(changeAmount) : null,
    beeper: null // Pas encore de numéro de beeper côté vente : ligne masquée
  }
})

const ticketPreviewHtml = computed(() => buildReceiptHTML({
  ...receiptData.value,
  items: props.items.map(item => ({
    name: item.name || item.product?.name || 'Article',
    price: Number(item.price) || 0,
    quantity: Number(item.quantity) || 1
  })),
  discount: discountAmount.value,
  total: finalTotal.value
}))

const onPreviewLoad = (event) => {
  const body = event.target?.contentDocument?.body
  if (body) previewHeight.value = Math.max(200, body.scrollHeight + 8)
}

watch(() => props.isOpen, (open) => { if (open) loadLogo() }, { immediate: true })

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
    ...receiptData.value,
    payments: props.payments?.map(p => ({
      method: p.payment_method_name || p.method || 'Espèces',
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

    const tableInfo = {
      name: props.tableName || 'Vente Directe',
      ticketNumber: invoiceData.number,
      serviceType: props.saleInfo?.sale?.service_type || null
    }
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
const printAndClose = async () => {
  await loadLogo()
  await printInvoicePDF()
  closeModal()
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
        ticketNumber: invoiceData.number,
        serviceType: props.saleInfo?.sale?.service_type || null // « SUR PLACE » / « À EMPORTER » en tête du bon
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
