<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm transition-opacity"
  >
    <div class="absolute inset-0" @click="closeModal" />

    <div class="relative mx-4 w-full max-w-xl rounded-3xl border border-slate-200 bg-white shadow-2xl">
      <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.3em] text-indigo-500">Fond de caisse</p>
          <h2 class="mt-2 text-lg font-semibold text-slate-900">Configurer le fond de caisse</h2>
          <p class="mt-1 text-sm text-slate-500">
            Renseignez le montant initial et le ticket de départ pour lancer la session.
          </p>
        </div>
        <button
          type="button"
          class="inline-flex size-9 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:border-rose-200 hover:text-rose-600"
          @click="closeModal"
          aria-label="Fermer"
        >
          <i class="fas fa-xmark"></i>
        </button>
      </header>

      <section class="space-y-5 px-6 py-6">
        <div>
          <label class="text-sm font-medium text-slate-700">Montant du fond de caisse</label>
          <input
            type="number"
            min="0"
            step="0.01"
            v-model.number="amount"
            @input="validateAmount"
            placeholder="Montant"
            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-200"
          />
          <p v-if="amountError" class="mt-1 text-sm font-medium text-rose-500">{{ amountError }}</p>
        </div>

        <div>
          <label class="text-sm font-medium text-slate-700">Numéro de ticket initial</label>
          <div class="relative mt-2">
            <select
              v-model.number="ticketNumber"
              @change="validateTicketNumber"
              class="w-full appearance-none rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-200"
            >
              <option value="">Sélectionnez un numéro</option>
              <option v-for="num in ticketNumbers" :key="num" :value="num">
                {{ num }}
              </option>
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-400">
              <i class="fas fa-chevron-down text-xs"></i>
            </span>
          </div>
          <p v-if="ticketError" class="mt-1 text-sm font-medium text-rose-500">{{ ticketError }}</p>
        </div>

        <div>
          <label class="text-sm font-medium text-slate-700">Note</label>
          <input
            type="text"
            v-model="note"
            maxlength="50"
            placeholder="Note (max 50 caractères)"
            class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-200"
          />
        </div>
      </section>

      <footer class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-5">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
          @click="closeModal"
          :disabled="isSending"
        >
          Annuler
        </button>

        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
          @click="sendAmount"
          :disabled="isSending || !!amountError || !!ticketError || amount === null || ticketNumber === null || ticketNumber === ''"
        >
          <i v-if="isSending" class="fas fa-circle-notch animate-spin text-xs"></i>
          Envoyer
        </button>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  isOpen: Boolean
})

const emits = defineEmits(['close', 'send'])

const ticketNumbers = [1000, 2000, 3000, 4000, 5000, 6000, 7000, 7300]
const amount = ref(null)
const ticketNumber = ref(null)
const note = ref('')
const amountError = ref('')
const ticketError = ref('')
const isSending = ref(false)

const validateAmount = () => {
  if (amount.value === null || amount.value === '') {
    amountError.value = 'Le montant est requis.'
  } else if (amount.value < 0) {
    amountError.value = 'Le montant doit être positif.'
  } else {
    amountError.value = ''
  }
}

const validateTicketNumber = () => {
  if (ticketNumber.value === null || ticketNumber.value === '') {
    ticketError.value = 'Le numéro de ticket est requis.'
  } else if (ticketNumber.value < 1000) {
    ticketError.value = 'Le numéro doit être au moins 1000.'
  } else {
    ticketError.value = ''
  }
}

const sendAmount = () => {
  validateAmount()
  validateTicketNumber()
  if (amountError.value || ticketError.value) return

  isSending.value = true

  const data = {
    amount: amount.value,
    ticketNumber: ticketNumber.value,
    note: note.value
  }

  emits('send', data)

  setTimeout(() => {
    isSending.value = false
    emits('close')
  }, 1000)
}

const closeModal = () => {
  emits('close')
}
</script>
