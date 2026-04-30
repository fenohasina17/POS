<template>
  <div
    class="keyboard rounded-3xl border border-slate-300 bg-slate-900 p-4 text-white shadow-2xl"
    :style="{ position: 'fixed', top: position.top + 'px', left: position.left + 'px' }"
  >
    <!-- Drag header -->
    <div
      class="mb-3 flex cursor-move items-center justify-between"
      @mousedown.stop.prevent="startDrag"
      @touchstart.stop.prevent="startDrag"
    >
      <div class="flex items-center">
        <span class="mr-2 text-gray-400">
          <i class="fas fa-arrows-alt"></i>
        </span>
        <strong>Clavier Virtuel</strong>
      </div>
      <div class="flex gap-2">
        <button
          type="button"
          class="flex items-center justify-center rounded border border-gray-500 bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700"
          @click="closeKeyboard"
          title="Fermer le clavier"
        >
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>

    <!-- Chiffres -->
    <div class="mb-2 flex flex-wrap justify-center gap-2">
      <button
        v-for="n in ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0']"
        :key="n"
        class="min-w-[45px] h-[45px] rounded-lg bg-slate-100 px-2 text-lg font-medium text-slate-800 transition hover:bg-indigo-600 hover:text-white"
        @click.stop="pressKey(n)"
      >
        {{ n }}
      </button>
    </div>

    <!-- Lettres -->
    <div
      v-for="(row, index) in layout"
      :key="index"
      class="mb-2 flex flex-wrap justify-center gap-2"
    >
      <button
        v-for="key in row"
        :key="key"
        class="min-w-[45px] h-[45px] rounded-lg bg-slate-100 px-2 text-lg font-medium text-slate-800 transition hover:bg-indigo-600 hover:text-white"
        @click.stop="pressKey(key)"
      >
        {{ formatKey(key) }}
      </button>
    </div>

    <!-- Dernière ligne -->
    <div class="flex flex-wrap justify-center gap-2">
      <button class="min-w-[45px] h-[45px] rounded-lg bg-blue-600 px-2 text-lg font-medium text-white transition hover:bg-blue-700" @click.stop="toggleShift">⇧ Shift</button>
      <button class="min-w-[45px] h-[45px] rounded-lg bg-yellow-500 px-2 text-lg font-medium text-black transition hover:bg-yellow-600" @click.stop="backspace">DELL</button>
      <button class="min-w-[100px] h-[45px] flex-grow rounded-lg bg-green-600 px-2 text-lg font-medium text-white transition hover:bg-green-700" @click.stop="pressKey(' ')">Espace</button>
    </div>
  </div>
</template>

<script setup>
defineOptions({ name: 'VirtualKeyboard' })
import { ref, onMounted, watch } from 'vue'

const props = defineProps({
  initialPosition: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['key-pressed', 'close'])

const isUppercase = ref(false)
const isDragging = ref(false)
const keyboardWidth = 640
const keyboardHeight = 280
const position = ref({ top: 600, left: 0 })

onMounted(() => {
  if (props.initialPosition && Number.isFinite(props.initialPosition.top) && Number.isFinite(props.initialPosition.left)) {
    position.value = {
      top: props.initialPosition.top,
      left: props.initialPosition.left
    }
    return
  }

  position.value.left = Math.max(16, window.innerWidth / 2 - keyboardWidth / 2)
  position.value.top = window.innerHeight - keyboardHeight - 16
})

let offset = { x: 0, y: 0 }

watch(
  () => props.initialPosition,
  (nextPosition) => {
    if (!nextPosition || isDragging.value) return
    const { top, left } = nextPosition
    if (!Number.isFinite(top) || !Number.isFinite(left)) return
    position.value = { top, left }
  },
  { deep: true }
)

const layout = [
  ['a', 'z', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
  ['q', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm'],
  ['w', 'x', 'c', 'v', 'b', 'n', '.', '@']
]

function formatKey(key) {
  return isUppercase.value ? key.toUpperCase() : key
}

function pressKey(key) {
  emit('key-pressed', formatKey(key))
}

function toggleShift() {
  isUppercase.value = !isUppercase.value
}

function backspace() {
  emit('key-pressed', 'BACKSPACE')
}

function startDrag(e) {
  const point = getPoint(e)
  if (!point) return
  isDragging.value = true
  offset = {
    x: point.x - position.value.left,
    y: point.y - position.value.top
  }
  document.addEventListener('mousemove', onDrag)
  document.addEventListener('mouseup', stopDrag)
  document.addEventListener('touchmove', onDrag, { passive: false })
  document.addEventListener('touchend', stopDrag)
}

function onDrag(e) {
  if (isDragging.value) {
    const point = getPoint(e)
    if (!point) return
    const margin = 8
    const widthBoundary = window.innerWidth - keyboardWidth - margin
    const heightBoundary = window.innerHeight - keyboardHeight - margin
    position.value.left = Math.min(Math.max(point.x - offset.x, margin), widthBoundary)
    position.value.top = Math.min(Math.max(point.y - offset.y, margin), heightBoundary)
  }
}

function stopDrag() {
  isDragging.value = false
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDrag)
  document.removeEventListener('touchmove', onDrag)
  document.removeEventListener('touchend', stopDrag)
}

function closeKeyboard() {
  emit('close')
}

function getPoint(event) {
  if ('touches' in event && event.touches.length > 0) {
    return { x: event.touches[0].clientX, y: event.touches[0].clientY }
  }
  if ('clientX' in event) {
    return { x: event.clientX, y: event.clientY }
  }
  return null
}
</script>
