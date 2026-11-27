/**
 * Routage des bons vers les imprimantes de la machine (réglable par caisse).
 *
 * Chaque poste (caisse, cuisine, fast food, pizza, bar) peut être envoyé sur
 * n'importe quelle imprimante détectée : utile en cas de panne ou quand la
 * caisse n'a qu'une imprimante. Module pur, partagé par Electron (main.cjs,
 * import dynamique) et l'écran de réglage.
 */
import { STATION_LABELS } from './receiptTemplate.js'

/** Postes réglables, dans l'ordre de l'écran */
export const STATIONS = Object.freeze(
  ['receipt', 'kitchen', 'fastfood', 'pizza', 'bar'].map((id) => ({
    id,
    label: id === 'receipt' ? 'Caisse (ticket client)' : STATION_LABELS[id],
  }))
)

const PREP_STATIONS = ['kitchen', 'cook', 'fastfood', 'pizza', 'bar']

/** Garde seulement les postes connus et des noms d'imprimante non vides */
export function normalizeRouting(routing) {
  const clean = {}
  for (const { id } of STATIONS) {
    const name = routing && typeof routing[id] === 'string' ? routing[id].trim() : ''
    if (name) clean[id] = name
  }
  return clean
}

/** « Tout sur une seule imprimante » : même imprimante pour chaque poste */
export function routeAllTo(printerName) {
  return normalizeRouting(Object.fromEntries(STATIONS.map(({ id }) => [id, printerName])))
}

/**
 * Choisit l'imprimante d'un poste.
 *  1. imprimante réglée pour ce poste (si elle est bien détectée) ;
 *  2. imprimante portant le nom du poste (« kitchen », « bar »… — comportement historique) ;
 *  3. fast food : imprimante de la cuisine ;
 *  4. bons de préparation et ticket : imprimante de la caisse (réglée, nommée
 *     « receipt », ou reconnue : XP-80C, thermal, pos…), sinon celle par défaut.
 *
 * @param {string} station  'receipt' | 'kitchen' | 'fastfood' | 'pizza' | 'bar' | …
 * @param {object} routing  { [station]: nom d'imprimante }
 * @param {Array<{name:string,isDefault?:boolean}>} printers  imprimantes détectées
 * @returns {{ name: string|null, reason: string }}
 */
export function resolvePrinter(station, routing = {}, printers = []) {
  const names = new Set((printers || []).map((p) => p.name))
  const has = (n) => !!n && names.has(n)
  const routed = (s) => (routing && has(routing[s]) ? routing[s] : null)
  const requested = station || 'receipt'

  if (routed(requested)) return { name: routing[requested], reason: 'réglage' }
  if (has(requested)) return { name: requested, reason: 'nom' }

  if (requested === 'fastfood') {
    const kitchen = routed('kitchen') || (has('kitchen') ? 'kitchen' : null)
    if (kitchen) return { name: kitchen, reason: 'repli cuisine' }
  }

  if (requested === 'receipt' || PREP_STATIONS.includes(requested)) {
    const receipt = routed('receipt') || (has('receipt') ? 'receipt' : null)
    if (receipt) return { name: receipt, reason: 'repli caisse' }

    const byKeyword = (printers || []).find((p) => /xp-80c|thermal|receipt|pos/i.test(p.name))
    const fallback = byKeyword || (printers || []).find((p) => p.isDefault) || (printers || [])[0]
    if (fallback) return { name: fallback.name, reason: 'repli caisse' }
  }

  return { name: null, reason: 'aucune imprimante' }
}
