import { describe, it, expect } from 'vitest'
import {
  buildReceiptHTML, formatTicketAmount, formatTicketDate, formatServiceLabel, serviceTypeBanner,
  summarizeServiceType, splitItemsByServiceType, SERVICE_TYPES
} from '../receiptTemplate'

const base = {
  ticketNumber: 1146,
  posCode: '108',
  cashierName: 'Safidy',
  cashRegisterName: 'Caisse 5',
  date: new Date(2026, 10, 3, 14, 8, 51),
  serviceLabel: 'A emporter',
  payments: [{ method: 'Espèce', amount: 5000 }],
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
      'Caissier :', 'Safidy', '03/11/2026 14:08:51', 'Caisse 5',
      'A emporter', 'Désignation', 'P.U', 'Qté', 'Montant',
      'Mojito', 'Net à payer', '3,000.00 Ar', 'Billet :', '5,000.00 Ar', 'Rendu :', '2,000.00 Ar'
    ]) {
      expect(html).toContain(text)
    }
  })

  it('détaille chaque paiement en bas et retire « Payé en » en haut', () => {
    const html = buildReceiptHTML({
      ...base,
      total: 20000,
      payments: [{ method: 'Espèce', amount: 1000 }, { method: 'Airtel Money', amount: 19000 }],
      changeAmount: 0
    })
    expect(html).not.toContain('Payé en')
    expect(html).toContain('<span>Billet :</span><span>1,000.00 Ar</span>')
    expect(html).toContain('<span>Airtel Money :</span><span>19,000.00 Ar</span>')
    expect(html).toContain('<span>Rendu :</span><span>0.00 Ar</span>')
  })

  it('n\'affiche « Serveur » que si un serveur est fourni', () => {
    expect(buildReceiptHTML(base)).not.toContain('Serveur')
    expect(buildReceiptHTML({ ...base, serviceLabel: 'Table 4', serverName: 'Hery' })).toContain('<div class="label">Serveur :</div><div>Hery</div>')
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

describe('sur place / à emporter', () => {
  it('affiche le choix du client, suivi de la table s\'il y en a une', () => {
    expect(formatServiceLabel({ serviceType: 'a_emporter' })).toBe('A emporter')
    expect(formatServiceLabel({ serviceType: 'sur_place' })).toBe('Sur place')
    expect(formatServiceLabel({ serviceType: 'sur_place', tableNumber: 4 })).toBe('Sur place – Table 4')
    expect(formatServiceLabel({ serviceType: 'a_emporter', tableNumber: '12' })).toBe('A emporter – Table 12')
  })

  it('ventes antérieures sans choix : déduit de la table comme avant', () => {
    expect(formatServiceLabel({ serviceType: null, tableNumber: 4 })).toBe('Table 4')
    expect(formatServiceLabel({ serviceType: null })).toBe('A emporter')
    // l'API renvoie table_number « Emporter » quand il n'y a pas de table
    expect(formatServiceLabel({ serviceType: 'sur_place', tableNumber: 'Emporter' })).toBe('Sur place')
  })

  it('donne la mention en gros des bons de préparation', () => {
    expect(serviceTypeBanner(SERVICE_TYPES.SUR_PLACE)).toBe('SUR PLACE')
    expect(serviceTypeBanner(SERVICE_TYPES.A_EMPORTER)).toBe('À EMPORTER')
    expect(serviceTypeBanner(null)).toBe('')
  })

  it('imprime le libellé sur le ticket client', () => {
    const html = buildReceiptHTML({ ...base, serviceLabel: formatServiceLabel({ serviceType: 'sur_place', tableNumber: 4 }) })
    expect(html).toContain('Sur place – Table 4')
  })

  it('commande mixte : pas de libellé global, seulement la table', () => {
    expect(formatServiceLabel({ serviceType: 'mixte', tableNumber: 4 })).toBe('Table 4')
    expect(formatServiceLabel({ serviceType: 'mixte' })).toBe('')
  })

  it('résume les articles : sur place, à emporter ou mixte', () => {
    expect(summarizeServiceType([{ serviceType: 'sur_place' }, { serviceType: 'sur_place' }])).toBe('sur_place')
    expect(summarizeServiceType([{ serviceType: 'sur_place' }, { serviceType: 'a_emporter' }])).toBe('mixte')
    expect(summarizeServiceType([{}])).toBeNull()
  })

  it('découpe les bons de préparation en « SUR PLACE » puis « À EMPORTER »', () => {
    const sections = splitItemsByServiceType([
      { name: 'Pizza Reine', serviceType: 'a_emporter' },
      { name: 'Mojito', serviceType: 'sur_place' },
      { name: 'Coca' } // sans mode : celui de la commande
    ], 'sur_place')
    expect(sections.map((s) => s.banner)).toEqual(['SUR PLACE', 'À EMPORTER'])
    expect(sections[0].items.map((i) => i.name)).toEqual(['Mojito', 'Coca'])
    expect(sections[1].items.map((i) => i.name)).toEqual(['Pizza Reine'])
  })

  it('bon sans aucune information (ancienne caisse) : une seule section sans mention', () => {
    const sections = splitItemsByServiceType([{ name: 'Mojito' }], null)
    expect(sections).toHaveLength(1)
    expect(sections[0].banner).toBe('')
  })

  it('ticket d\'une commande mixte : les articles à emporter sont signalés', () => {
    const html = buildReceiptHTML({
      ...base,
      items: [
        { name: 'Mojito', price: 3000, quantity: 1, serviceType: 'sur_place' },
        { name: 'Pizza Reine', price: 20000, quantity: 1, serviceType: 'a_emporter' }
      ]
    })
    expect(html.match(/› à emporter/g)).toHaveLength(1)
    expect(html.indexOf('› à emporter')).toBeGreaterThan(html.indexOf('Pizza Reine'))
  })

  it('ticket tout à emporter : pas de mention ligne par ligne', () => {
    const html = buildReceiptHTML({ ...base, items: [{ name: 'Mojito', price: 3000, quantity: 1, serviceType: 'a_emporter' }] })
    expect(html).not.toContain('› à emporter')
  })
})
