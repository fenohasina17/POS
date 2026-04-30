// utils/db.js
import { openDB } from 'idb'

const DB_NAME = 'pos_offline_db'
const DB_VERSION = 1

export const initDB = async () => {
  return openDB(DB_NAME, DB_VERSION, {
    upgrade(db) {
      if (!db.objectStoreNames.contains('temp_sales')) {
        const saleStore = db.createObjectStore('temp_sales', { keyPath: 'id' })
        saleStore.createIndex('created_at', 'created_at')
        saleStore.createIndex('status', 'status')
      }
      
      if (!db.objectStoreNames.contains('temp_payments')) {
        const paymentStore = db.createObjectStore('temp_payments', { keyPath: 'id' })
        paymentStore.createIndex('sale_id', 'sale_id')
        paymentStore.createIndex('created_at', 'created_at')
      }
    }
  })
}

export const saveSaleToLocal = async (saleData) => {
  const db = await initDB()
  const id = `temp_${Date.now()}_${Math.random().toString(36).substr(2, 8)}`
  
  const sale = {
    id: id,
    ...saleData,
    status: 'pending',
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  }
  
  await db.add('temp_sales', sale)
  return sale
}

export const addPaymentToLocal = async (saleId, paymentData) => {
  const db = await initDB()
  const id = `pay_${Date.now()}_${Math.random().toString(36).substr(2, 8)}`
  
  const payment = {
    id: id,
    sale_id: saleId,
    ...paymentData,
    created_at: new Date().toISOString()
  }
  
  await db.add('temp_payments', payment)
  return payment
}

export const getSalePayments = async (saleId) => {
  const db = await initDB()
  const index = db.transaction('temp_payments').store.index('sale_id')
  return await index.getAll(saleId)
}

export const deleteLocalSale = async (saleId) => {
  const db = await initDB()
  const payments = await getSalePayments(saleId)
  for (const payment of payments) {
    await db.delete('temp_payments', payment.id)
  }
  await db.delete('temp_sales', saleId)
}