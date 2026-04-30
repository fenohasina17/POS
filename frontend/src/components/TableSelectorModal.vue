<template>
  <div v-if="isOpen" class="table-selector-modal">
    <div class="modal-backdrop" @click="close"></div>
    <div class="modal-content">
      <TableSelector
        :is-open="isOpen"
        @close="close"
        @table-selected="onTableSelected"
      />
    </div>
  </div>
</template>

<script>
import TableSelector from '../views/TableSelector.vue'

export default {
  name: 'TableSelectorModal',
  components: {
    TableSelector
  },
  props: {
    isOpen: {
      type: Boolean,
      default: false
    }
  },
  methods: {
    close() {
      this.$emit('close')
    },
    onTableSelected(table) {
      this.$emit('table-selected', table)
    }
  }
}
</script>

<style scoped>
.table-selector-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-backdrop {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
}

.modal-content {
  position: relative;
  z-index: 1001;
  width: 90vw;
  max-width: 800px;
  max-height: 80vh;
}
</style>
