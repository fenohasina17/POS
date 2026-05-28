// services/tableService.js - Facade Pattern
import { TableRepository } from './repositories/TableRepository'
export const tableRepository = new TableRepository();
import { orderFactory } from './factories/OrderFactory'
import { EventBus } from '@/services/EventBus'
import apiClient from './apiClient'

class TableService {
  constructor() {
    this.repository = tableRepository
    this.factory = orderFactory
    this.eventBus = EventBus
  }

  async getAllTables(forceRefresh = false) {
    return this.repository.getAll(forceRefresh)
  }

  async getTableById(id, forceRefresh = false) {
    return this.repository.getById(id, forceRefresh)
  }

  async getTableFullContext(id) {
    return this.repository.getTableFullContext(id)
  }

  async updateTableStatus(id, status) {
    const result = await this.repository.updateStatus(id, status)
    this.eventBus.emit('table:status-changed', { id, status })
    return result
  }

  async getPendingOrders(tableId) {
    return this.repository.getPendingOrders(tableId)
  }

  async createPendingOrder(tableId, userId, pointOfSaleId, sessionId, cartItems) {
    const orderData = this.factory.createPendingOrder(
      tableId, userId, pointOfSaleId, sessionId, cartItems
    )

    const { data } = await apiClient.post('/sales/pending-order', orderData)

    // Invalidate caches
    this.eventBus.emit('pending-order:created', { tableId })

    return data.data || data
  }

  async addProductsToPendingOrder(saleId, cartItems, tableId) {
    const payload = this.factory.createAddProductsPayload(saleId, cartItems)
    const token = localStorage.getItem('token')

    const response = await apiClient.post(`/sales/${saleId}/add-products`, {
      order_lines: payload.order_lines
    })

    this.eventBus.emit('pending-order:updated', { saleId })
    // Emit also table status changed to refresh status in list
    this.eventBus.emit('table:status-changed', { id: tableId, status: 'occupied' })

    return response.data
  }

  invalidateTableCache(id) {
    this.repository._invalidateTableCache(id)
  }
  async updateTableStatus(id, status) {
    EventBus.emit('table:status-changed', { id, status })
  }
}

export const tableService = new TableService()
