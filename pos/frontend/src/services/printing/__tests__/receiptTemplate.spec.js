import { describe, it, expect } from 'vitest'
import { buildReceiptHTML, formatTicketAmount, formatTicketDate } from '../receiptTemplate'

const base = {
  ticketNumber: 1146,
  posCode: '108',
  cashierName: 'Safidy',
  cashRegisterName: 'Caisse 5',
  date: new Date(2026, 10, 3, 14, 8, 51),
  serviceLabel: 'A emporter',
  paymentLabel: 'Espece',
  items: [{ name: 'Mojito', price: 3000, quantity: 1 }],
  total: 3000,
  amountReceived: 5000,
  changeAmount: 2000
}

describe('receiptTemplate', () => {
  it('formate les montants comme le modèle (3,000.00)', () => {
    expect(formatTicketAmount(3000)).toBe('3,000.00')
    expect(formatTicketAmount('150000.5')).toBe('150,000.50')
    expect(formatTicketAmount(undefined)).toBe('0.00')
  })

  it('formate la date en jj/mm/aaaa hh:mm:ss', () => {
    expect(formatTicketDate(new Date(2026, 10, 3, 14, 8, 51))).toBe('03/11/2026 14:08:51')
  })

  it('reprend tous les blocs du modèle', () => {
    const html = buildReceiptHTML(base)
    for (const text of [
      'LA GASTRONOMIE PIZZA', 'Ticket N°', '1146', 'POINT DE VENTE : 108',
      'Caissier :', 'Safidy', 'Serveur :', '03/11/2026 14:08:51', 'Caisse 5',
      'A emporter', 'Payé en Espece', 'Désignation', 'P.U', 'Qté', 'Montant',
      'Mojito', 'Net à payer', '3,000.00 Ar', 'Billet :', '5,000.00 Ar', 'Rendu :', '2,000.00 Ar'
    ]) {
      expect(html).toContain(text)
    }
  })

  it('masque la ligne Beeper sans donnée et l\'affiche sinon', () => {
    expect(buildReceiptHTML(base)).not.toContain('Beeper')
    expect(buildReceiptHTML({ ...base, beeper: 0 })).toContain('Beeper 0')
  })

  it('échappe le HTML des libellés', () => {
    const html = buildReceiptHTML({ ...base, items: [{ name: '<b>x</b>', price: 1, quantity: 1 }] })
    expect(html).toContain('&lt;b&gt;x&lt;/b&gt;')
  })
})
