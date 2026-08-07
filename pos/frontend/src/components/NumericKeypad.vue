<template>
  <div class="keypad">
    <div v-for="row in rows" :key="row.join('')" class="keypad-row">
      <button
        v-for="key in row"
        :key="key"
        type="button"
        class="keypad-btn"
        :class="{
          danger: key === 'DEL',
          disabled: key !== 'DEL' && disabled
        }"
        :disabled="key !== 'DEL' && disabled"
        @click="onKeyClick(key)"
      >
        <font-awesome-icon v-if="key === 'DEL'" icon="fa-solid fa-delete-left" />
        <span v-else>{{ key }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faDeleteLeft } from '@fortawesome/free-solid-svg-icons'

// Add the icon to the library for this component
library.add(faDeleteLeft)

const props = defineProps({
  rows: {
    type: Array,
    default: () => [
      ['7', '8', '9'],
      ['4', '5', '6'],
      ['1', '2', '3'],
      ['0', '•', 'DEL'],
    ]
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['press', 'delete'])

const onKeyClick = (key) => {
  if (key === 'DEL') {
    emit('delete')
  } else {
    emit('press', key)
  }
}
</script>

<style scoped>
.keypad {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.keypad-row {
  display: flex;
  gap: 0.4rem;
  justify-content: center;
}

.keypad-btn {
  width: 76px;
  height: 58px;
  border-radius: 0.75rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
  cursor: pointer;
  transition: all 0.12s;
}

.keypad-btn:hover:not(:disabled) {
  background: #eef2ff;
  border-color: #a5b4fc;
  color: #4f46e5;
}
.keypad-btn:active:not(:disabled) {
  transform: scale(0.95);
}
.keypad-btn.danger {
  color: #e11d48;
}
.keypad-btn.danger:hover {
  background: #fff1f2;
  border-color: #fda4af;
}
.keypad-btn.disabled,
.keypad-btn:disabled {
  opacity: 0.3;
  cursor: default;
}
</style>
