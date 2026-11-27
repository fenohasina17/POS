import { describe, it, expect } from 'vitest'
import { resolvePrinter, routeAllTo, normalizeRouting, STATIONS } from '../printerRouting'

const printers = [
  { name: 'receipt', isDefault: true },
  { name: 'kitchen' },
  { name: 'bar' },
  { name: 'EPSON TM-T20' },
]

describe('routage des imprimantes', () => {
  it('sans réglage : chaque poste va sur l\'imprimante qui porte son nom (comme avant)', () => {
    expect(resolvePrinter('kitchen', {}, printers).name).toBe('kitchen')
    expect(resolvePrinter('bar', {}, printers).name).toBe('bar')
    expect(resolvePrinter('receipt', {}, printers).name).toBe('receipt')
  })

  it('le réglage de la caisse l\'emporte', () => {
    const routing = { bar: 'EPSON TM-T20' }
    expect(resolvePrinter('bar', routing, printers)).toEqual({ name: 'EPSON TM-T20', reason: 'réglage' })
    expect(resolvePrinter('kitchen', routing, printers).name).toBe('kitchen')
  })

  it('panne : tout peut sortir sur une seule imprimante', () => {
    const routing = routeAllTo('EPSON TM-T20')
    for (const { id } of STATIONS) {
      expect(resolvePrinter(id, routing, printers).name).toBe('EPSON TM-T20')
    }
  })

  it('imprimante réglée mais débranchée : repli automatique', () => {
    expect(resolvePrinter('bar', { bar: 'Imprimante HS' }, printers).name).toBe('bar')
    expect(resolvePrinter('pizza', { pizza: 'Imprimante HS' }, printers)).toEqual({ name: 'receipt', reason: 'repli caisse' })
  })

  it('fast food sans imprimante dédiée : cuisine (ou l\'imprimante réglée pour la cuisine)', () => {
    expect(resolvePrinter('fastfood', {}, printers)).toEqual({ name: 'kitchen', reason: 'repli cuisine' })
    expect(resolvePrinter('fastfood', { kitchen: 'EPSON TM-T20' }, printers).name).toBe('EPSON TM-T20')
  })

  it('poste sans imprimante : imprimante de la caisse réglée, sinon reconnue, sinon par défaut', () => {
    expect(resolvePrinter('pizza', { receipt: 'EPSON TM-T20' }, printers).name).toBe('EPSON TM-T20')
    expect(resolvePrinter('pizza', {}, [{ name: 'XP-80C' }, { name: 'Bureau', isDefault: true }]).name).toBe('XP-80C')
    expect(resolvePrinter('pizza', {}, [{ name: 'Bureau', isDefault: true }]).name).toBe('Bureau')
    expect(resolvePrinter('pizza', {}, []).name).toBeNull()
  })

  it('le réglage enregistré ne garde que les postes connus et les noms non vides', () => {
    expect(normalizeRouting({ bar: ' EPSON ', kitchen: '', inconnu: 'X', pizza: 3 })).toEqual({ bar: 'EPSON' })
    expect(normalizeRouting(null)).toEqual({})
  })
})
