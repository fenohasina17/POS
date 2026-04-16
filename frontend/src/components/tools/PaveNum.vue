<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="close">
    <div class="flex w-[300px] flex-col items-center rounded-xl bg-white p-4 shadow-2xl">
      <div class="mb-4 h-12 w-full select-none rounded-lg border border-gray-300 px-4 py-2 text-right text-xl">{{ displayValue }}</div>
      <div class="grid w-full grid-cols-3 gap-2">
        <button
          v-for="n in numbers"
          :key="n"
          @click="appendNumber(n)"
          class="select-none rounded-lg border border-gray-300 bg-white py-2 text-lg hover:bg-gray-100 active:bg-gray-200"
        >
          {{ n }}
        </button>
        <button @click="clear" class="rounded-lg bg-red-600 py-2 text-lg font-semibold text-white hover:bg-red-700">C</button>
        <button @click="confirm" class="rounded-lg bg-green-600 py-2 text-lg font-semibold text-white hover:bg-green-700">OK</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, defineEmits, defineProps } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue', 'close', 'confirm'])

const displayValue = ref(props.modelValue)

watch(() => props.modelValue, (newVal) => {
  displayValue.value = newVal
})

const numbers = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0']

const appendNumber = (num) => {
  displayValue.value += num
  emit('update:modelValue', displayValue.value)
}

const clear = () => {
  displayValue.value = ''
  emit('update:modelValue', displayValue.value)
}

const confirm = () => {
  emit('confirm', displayValue.value)
  close()
}

const close = () => {
  emit('close')
}
</script>

<style scoped>
</style>
