<template>
  <div class="p-6">
    <h1 class="text-xl font-bold mb-6">Imprimantes détectées</h1>
    <div v-if="loading" class="text-sm">Détection en cours...</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="printerName in printers" :key="printerName" class="p-4 border rounded-xl bg-white shadow-sm">
        <h3 class="font-semibold">{{ printerName }}</h3>
        <div class="mt-3 flex gap-2">
          <button 
            @click="testPrint(printerName)"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700"
          >
            Imprimer Bon Test
          </button>
          <button 
            @click="setAsDefault(printerName)"
            class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700"
          >
            Définir par défaut
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { printingService } from '@/services/printing/PrintingService'

const printers = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    // Utilisation directe de window.qz pour le test si disponible
    if (window.qz) {
      await printingService.qzTrayAdapter.connect()
      printers.value = await window.qz.printers.list()
    } else {
      alert("La bibliothèque QZ Tray n'est pas chargée. Vérifiez index.html.")
    }
  } catch (err) {
    console.error('Erreur détection imprimantes:', err)
  } finally {
    loading.value = false
  }
})

const testPrint = async (name) => {
  const dummyTable = { name: 'TABLE TEST' }
  const dummyItems = [{ name: 'PRODUIT TEST', quantity: 1 }]
  try {
    await printingService.sendRawCommands(
      printingService.qzTrayAdapter.formatEscPosOrder(dummyTable, dummyItems),
      name
    )
    alert('Impression envoyée vers ' + name)
  } catch (err) {
    alert('Erreur: ' + err.message)
  }
}

const setAsDefault = (name) => {
  localStorage.setItem('cashPrinterName', name)
  localStorage.setItem('kitchenPrinterName', name)
  alert('Imprimante définie par défaut : ' + name)
}
</script>
