<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm transition-opacity"
  >
    <div class="absolute inset-0" @click="closeModal" />

    <div ref="modalRef" class="relative mx-4 w-full max-w-xl rounded-3xl border border-slate-200 bg-white shadow-2xl">
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
            ref="amountInput"
            type="text"
            v-model="amount"
            @focus="showKeyboard('amount', $event)"
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
              ref="ticketInput"
              v-model.number="ticketNumber"
              @change="validateTicketNumber"
              @focus="hideKeyboard"
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
            ref="noteInput"
            type="text"
            v-model="note"
            @focus="showKeyboard('note', $event)"
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
          :disabled="isSending || !!amountError || !!ticketError || amount === null || amount === '' || ticketNumber === null || ticketNumber === ''"
        >
          <i v-if="isSending" class="fas fa-circle-notch animate-spin text-xs"></i>
          Envoyer
        </button>
      </footer>
    </div>
  </div>

  <Teleport to="body">
    <Keyboard
      v-if="keyboardVisible"
      :initial-position="keyboardPosition"
      @key-pressed="handleKeyPress"
      @close="hideKeyboard"
      class="z-[100]"
    />
  </Teleport></template>

<script setup>
import { ref, reactive, nextTick, onBeforeUnmount, watch } from 'vue'
import Keyboard from '../components/tools/Keyboard.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faDeleteLeft, faXmark, faChevronDown, faCircleNotch } from '@fortawesome/free-solid-svg-icons'

library.add(faDeleteLeft, faXmark, faChevronDown, faCircleNotch)

const props = defineProps({
  isOpen: Boolean
})

// ... dans le template, mettez à jour l'icône :
// <FontAwesomeIcon v-if="key === 'DEL'" icon="fa-delete-left" />

const emits = defineEmits(['close', 'send'])

const ticketNumbers = [1000, 2000, 3000, 4000, 5000, 6000, 7000, 7300]
const amount = ref('')
const ticketNumber = ref('')
const note = ref('')
const amountError = ref('')
const ticketError = ref('')
const isSending = ref(false)

// Keyboard State
const keyboardVisible = ref(false)
const activeField = ref(null)
const keyboardPosition = ref({ top: 0, left: 0 })
const modalRef = ref(null)
const amountInput = ref(null)
const ticketInput = ref(null)
const noteInput = ref(null)

const inputRefs = {
  amount: amountInput,
  ticketNumber: ticketInput,
  note: noteInput
}

// Strategy Pattern: define how each field handles input
const inputStrategies = {
  amount: (current, key) => {
    if (key === 'BACKSPACE') return current.toString().slice(0, -1)
    if (/[0-9]/.test(key)) return current.toString() + key
    if (key === '.' || key === ',') {
      const normalizedKey = '.'
      if (!current.toString().includes(normalizedKey)) {
        return current.toString() + normalizedKey
      }
    }
    return current
  },
  ticketNumber: (current, key) => {
    if (key === 'BACKSPACE') return current.toString().slice(0, -1)
    if (/[0-9]/.test(key)) return current.toString() + key
    return current
  },
  note: (current, key) => {
    if (key === 'BACKSPACE') return current.slice(0, -1)
    if (key === 'Espace') return current + ' '
    if (key.length === 1) return current + key
    return current
  }
}

const hideKeyboard = () => {
  keyboardVisible.value = false
  activeField.value = null
}

const handleKeyPress = (key) => {
  if (!activeField.value) return

  const strategy = inputStrategies[activeField.value]
  if (!strategy) return

  if (activeField.value === 'amount') {
    amount.value = strategy(amount.value, key)
    validateAmount()
  } else if (activeField.value === 'ticketNumber') {
    ticketNumber.value = strategy(ticketNumber.value, key)
    validateTicketNumber()
  } else if (activeField.value === 'note') {
    note.value = strategy(note.value, key)
  }
}

const showKeyboard = async (field, event) => {
  activeField.value = field
  keyboardVisible.value = true
  await nextTick()
  updateKeyboardPosition(event.target)
}

const updateKeyboardPosition = (targetElement) => {
  const el = targetElement || document.activeElement
  if (!el) return

  const rect = el.getBoundingClientRect()
  const viewportWidth = window.innerWidth
  const viewportHeight = window.innerHeight

  const KEYBOARD_WIDTH = activeField.value?.type === 'note' ? 400 : 240
  const KEYBOARD_HEIGHT = 300
  const MARGIN = 16

  let top = rect.bottom + MARGIN
  let left = rect.left

  if (left + KEYBOARD_WIDTH > viewportWidth - MARGIN) left = viewportWidth - KEYBOARD_WIDTH - MARGIN
  if (top + KEYBOARD_HEIGHT > viewportHeight - MARGIN) top = rect.top - KEYBOARD_HEIGHT - MARGIN

  keyboardPosition.value = {
    top: Math.max(MARGIN, top),
    left: Math.max(MARGIN, Math.max(0, left))
  }
}
const validateAmount = () => {
  const val = parseFloat(amount.value)
  if (amount.value === null || amount.value === '') {
    amountError.value = 'Le montant est requis.'
  } else if (isNaN(val) || val < 0) {
    amountError.value = 'Le montant doit être un nombre positif.'
  } else {
    amountError.value = ''
  }
}

const validateTicketNumber = () => {
  const val = parseInt(ticketNumber.value)
  if (ticketNumber.value === null || ticketNumber.value === '') {
    ticketError.value = 'Le numéro de ticket est requis.'
  } else if (isNaN(val) || val < 1000) {
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
    amount: parseFloat(amount.value),
    ticketNumber: parseInt(ticketNumber.value),
    note: note.value
  }

  emits('send', data)

  setTimeout(() => {
    isSending.value = false
    emits('close')
    resetForm()
  }, 1000)
}

const resetForm = () => {
  amount.value = ''
  ticketNumber.value = ''
  note.value = ''
  amountError.value = ''
  ticketError.value = ''
  hideKeyboard()
}

const closeModal = () => {
  resetForm()
  emits('close')
}

// Watch for window resize to reposition keyboard
const handleResize = () => {
  if (keyboardVisible.value) {
    updateKeyboardPosition()
  }
}

window.addEventListener('resize', handleResize)
onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
})

watch(() => props.isOpen, (newVal) => {
  if (!newVal) {
    hideKeyboard()
  }
})
</script>
