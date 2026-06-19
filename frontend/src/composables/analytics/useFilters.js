import { ref, computed } from 'vue'

export function useFilters() {
  const selectedPointOfSale = ref('')
  const periodType = ref('month')
  const startDate = ref('')
  const endDate = ref('')

  const dateRange = computed(() => {
    const now = new Date()
    if (periodType.value === 'today') {
      const today = now.toISOString().split('T')[0]
      return { start: today, end: today }
    }
    // ... add logic for other ranges
    return { start: startDate.value, end: endDate.value }
  })

  return { selectedPointOfSale, periodType, startDate, endDate, dateRange }
}
