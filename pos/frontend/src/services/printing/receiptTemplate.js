/**
 * Modèle HTML du ticket client (papier thermique 80 mm, zone imprimable ~72 mm).
 *
 * Module pur (aucune dépendance) partagé par :
 *  - Electron (main.cjs, via import dynamique) pour l'impression réelle ;
 *  - l'interface Vue (InvoiceModal) pour l'aperçu à l'écran.
 *
 * Présentation uniquement : les montants reçus ici sont déjà calculés par la vente.
 */

export const escapeHtml = (value) =>
  String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')

/** 3000 -> "3,000.00" (format du modèle de ticket) */
export const formatTicketAmount = (value) => {
  const n = Number(value)
  const safe = Number.isFinite(n) ? n : 0
  return safe.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/** Paiement en espèces → affiché « Billet » comme sur le modèle */
export const isCashPayment = (name) => /esp[eè]ce|cash|billet/i.test(String(name ?? ''))

/** Date -> "03/11/2026 14:08:51" */
export const formatTicketDate = (value) => {
  const d = value instanceof Date ? value : new Date(value ?? Date.now())
  if (Number.isNaN(d.getTime())) return String(value ?? '')
  const p = (x) => String(x).padStart(2, '0')
  return `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
}

// ── « Sur place » / « À emporter » ──
// Choisi par article (order_lines.service_type) ; la vente le résume
// (sales.service_type), « mixte » quand la commande mélange les deux.
export const SERVICE_TYPES = Object.freeze({ SUR_PLACE: 'sur_place', A_EMPORTER: 'a_emporter', MIXTE: 'mixte' })

/** Libellé du ticket (graphie du modèle : « A emporter ») */
export const serviceTypeText = (serviceType) =>
  serviceType === SERVICE_TYPES.SUR_PLACE ? 'Sur place'
    : serviceType === SERVICE_TYPES.A_EMPORTER ? 'A emporter'
      : '' // « mixte » : pas de libellé global, les articles à emporter sont signalés ligne par ligne

/** Mention en gros en tête des bons de préparation cuisine / bar */
export const serviceTypeBanner = (serviceType) =>
  serviceType === SERVICE_TYPES.SUR_PLACE ? 'SUR PLACE'
    : serviceType === SERVICE_TYPES.A_EMPORTER ? 'À EMPORTER'
      : ''

/** Résumé d'une liste d'articles : sur_place, a_emporter, mixte ou null si aucun n'a l'info */
export function summarizeServiceType(items = []) {
  const types = new Set((items || []).map((i) => i?.serviceType).filter(Boolean))
  if (types.size === 0) return null
  return types.size > 1 ? SERVICE_TYPES.MIXTE : [...types][0]
}

/**
 * Bons de préparation : articles regroupés par mode, « SUR PLACE » d'abord.
 * Un article sans mode prend celui de la commande ($fallback).
 * @returns {Array<{serviceType, banner, items}>}
 */
export function splitItemsByServiceType(items = [], fallback = null) {
  const order = [SERVICE_TYPES.SUR_PLACE, SERVICE_TYPES.A_EMPORTER, null]
  const groups = new Map(order.map((t) => [t, []]))
  for (const item of items || []) {
    const t = item?.serviceType || (fallback !== SERVICE_TYPES.MIXTE ? fallback : null) || null
    groups.get(order.includes(t) ? t : null).push(item)
  }
  return order
    .filter((t) => groups.get(t).length)
    .map((t) => ({ serviceType: t, banner: serviceTypeBanner(t), items: groups.get(t) }))
}

/**
 * Libellé du ticket client : « Sur place – Table 4 », « A emporter »…
 * Commande mixte : aucun libellé global (seulement « Table 4 » s'il y a une table).
 * Ventes enregistrées avant le choix (serviceType vide) : déduit de la table comme avant.
 */
export function formatServiceLabel({ serviceType, tableNumber } = {}) {
  const hasTable = tableNumber !== null && tableNumber !== undefined && tableNumber !== '' && tableNumber !== 'Emporter'
  const table = hasTable ? `Table ${tableNumber}` : ''
  if (serviceType === SERVICE_TYPES.MIXTE) return table
  const text = serviceTypeText(serviceType)
  if (text) return table ? `${text} – ${table}` : text
  return table || 'A emporter'
}

// ── Postes de préparation (categories.printer) ──
export const STATION_LABELS = Object.freeze({ kitchen: 'Cuisine', cook: 'Cuisine', fastfood: 'Fast food', pizza: 'Pizza', bar: 'Bar', receipt: 'Caisse' })
const STATION_ORDER = ['kitchen', 'cook', 'fastfood', 'pizza', 'bar', 'receipt']

/** Nom lisible d'un poste : 'kitchen' -> « Cuisine » (inconnu : nom brut) */
export const stationLabel = (printer) => STATION_LABELS[printer] || String(printer || '')

/**
 * « Suite de commande » : quand un même ticket part sur plusieurs postes, chaque bon
 * cite les autres. Renvoie « Pizza + Bar » (sans doublon ni le poste courant), '' sinon.
 */
export function otherStationsText(stations = [], current = null) {
  const labels = []
  const sorted = [...new Set(stations || [])].sort((a, b) => {
    const ia = STATION_ORDER.indexOf(a), ib = STATION_ORDER.indexOf(b)
    return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib)
  })
  for (const s of sorted) {
    const label = stationLabel(s)
    if (s !== current && label && label !== stationLabel(current) && !labels.includes(label)) labels.push(label)
  }
  return labels.join(' + ')
}

export const RECEIPT_STYLES = `
  * { margin: 0; padding: 0; box-sizing: border-box; color: #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  @page { size: 80mm auto; margin: 0; }
  html, body { background: #fff; }
  /* Papier 80 mm : marges intérieures pour rester dans la zone imprimable (~72 mm) avec un blanc sur chaque bord */
  body { font-family: Arial, Helvetica, sans-serif; width: 80mm; padding: 8mm 6mm 8mm; font-size: 11px; line-height: 1.25; }
  .title { font-size: 15px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2px; text-align: center; margin-bottom: 3mm; }
  .head { display: flex; align-items: center; justify-content: space-between; gap: 2mm; margin-bottom: 3mm; }
  .logo { width: 38mm; height: auto; display: block; }
  .ticket { text-align: center; flex: 1; }
  .ticket-label { font-size: 12px; }
  .ticket-number { font-size: 26px; font-weight: 900; line-height: 1.05; }
  .pos { font-size: 12px; font-weight: 700; margin-bottom: 1mm; }
  .infos { display: flex; justify-content: space-between; gap: 2mm; margin-bottom: 3mm; }
  .infos .col { flex: 1; min-width: 0; }
  .infos .right { text-align: left; padding-left: 4mm; }
  .infos .label { font-weight: 700; }
  .infos div { word-break: break-word; }
  .infos .service { font-weight: 700; }
  td .tag { font-size: 10px; font-weight: 700; font-style: italic; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 2mm; }
  th { font-weight: 700; text-decoration: underline; text-align: left; padding: 0 0 1.5mm; font-size: 11px; }
  td { padding: 0.6mm 0; vertical-align: top; word-break: break-word; font-size: 11px; }
  .num { text-align: right; }
  .qty { text-align: center; }
  .totals { width: 100%; margin-top: 1mm; }
  .totals .row { display: flex; justify-content: flex-end; margin: 1.2mm 0; }
  .totals .row span:first-child { width: 24mm; }
  .totals .row span:last-child { width: 26mm; text-align: right; }
  .totals .net { font-weight: 700; }
  .totals .net span:last-child { text-decoration: underline; }
  .beeper { font-size: 22px; font-weight: 900; margin-top: 4mm; }
`

/**
 * @param {object} data
 * @param {string} [data.title]           Titre en tête (« LA GASTRONOMIE PIZZA »)
 * @param {string} [data.logoSrc]         URL ou data URL du logo
 * @param {string|number} data.ticketNumber
 * @param {string} [data.posCode]         Code du point de vente
 * @param {string} [data.cashierName]
 * @param {string} [data.serverName]      Serveur de la table (ligne masquée si absent)
 * @param {string|Date} [data.date]
 * @param {string} [data.cashRegisterName]
 * @param {string} [data.serviceLabel]    « A emporter » / « Sur place – Table 4 » (cf. formatServiceLabel)
 * @param {Array<{method,amount}>} [data.payments]  Détail des règlements (une ligne chacun en bas)
 * @param {Array<{name,price,quantity,serviceType}>} data.items  serviceType : sur_place | a_emporter (marqué si commande mixte)
 * @param {number} [data.discount]        Montant de la remise (déjà calculé)
 * @param {number} data.total             Net à payer (déjà calculé)
 * @param {number|null} [data.amountReceived]  Utilisé seulement sans détail des paiements
 * @param {number|null} [data.changeAmount]
 * @param {string|number|null} [data.beeper]  Ligne masquée si absent
 */
export function buildReceiptBody(data = {}) {
  const items = Array.isArray(data.items) ? data.items : []
  const has = (v) => v !== null && v !== undefined && v !== ''

  // Commande mixte : les articles à emporter sont signalés sous leur nom
  const markTakeaway = summarizeServiceType(items) === SERVICE_TYPES.MIXTE

  const rows = items
    .map((item) => {
      const qty = Number(item.quantity) || 0
      const price = Number(item.price) || 0
      const tag = markTakeaway && item.serviceType === SERVICE_TYPES.A_EMPORTER ? '<div class="tag">› à emporter</div>' : ''
      return `<tr>
        <td>${escapeHtml(item.name)}${tag}</td>
        <td class="num">${formatTicketAmount(price)}</td>
        <td class="qty">${qty}</td>
        <td class="num">${formatTicketAmount(price * qty)}</td>
      </tr>`
    })
    .join('')

  // Une ligne par règlement : « Billet : » pour les espèces, sinon le nom du mode (Airtel Money…)
  const payments = (Array.isArray(data.payments) ? data.payments : []).filter((p) => Number(p?.amount) > 0)
  const paymentRows = payments.length
    ? payments
        .map((p) => {
          const label = isCashPayment(p.method) ? 'Billet' : (p.method || 'Paiement')
          return `<div class="row"><span>${escapeHtml(label)} :</span><span>${formatTicketAmount(p.amount)} Ar</span></div>`
        })
        .join('')
    : has(data.amountReceived)
      ? `<div class="row"><span>Billet :</span><span>${formatTicketAmount(data.amountReceived)} Ar</span></div>`
      : ''

  return `
    <div class="title">${escapeHtml(data.title || 'LA GASTRONOMIE PIZZA')}</div>
    <div class="head">
      ${data.logoSrc ? `<img class="logo" src="${escapeHtml(data.logoSrc)}" alt="">` : '<span></span>'}
      <div class="ticket">
        <div class="ticket-label">Ticket N°</div>
        <div class="ticket-number">${escapeHtml(data.ticketNumber ?? '')}</div>
      </div>
    </div>
    <div class="pos">POINT DE VENTE : ${escapeHtml(data.posCode ?? '')}</div>
    <div class="infos">
      <div class="col">
        <div class="label">Caissier :</div>
        <div>${escapeHtml(data.cashierName ?? '')}</div>
        ${has(data.serverName) ? `<div class="label">Serveur :</div><div>${escapeHtml(data.serverName)}</div>` : ''}
      </div>
      <div class="col right">
        <div>${escapeHtml(formatTicketDate(data.date))}</div>
        ${has(data.cashRegisterName) ? `<div>${escapeHtml(data.cashRegisterName)}</div>` : ''}
        ${has(data.serviceLabel) ? `<div class="service">${escapeHtml(data.serviceLabel)}</div>` : ''}
      </div>
    </div>
    <table>
      <colgroup><col style="width:38%"><col style="width:24%"><col style="width:10%"><col style="width:28%"></colgroup>
      <thead><tr><th>Désignation</th><th class="num">P.U</th><th class="qty">Qté</th><th class="num">Montant</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>
    <div class="totals">
      ${Number(data.discount) > 0 ? `<div class="row"><span>Remise</span><span>-${formatTicketAmount(data.discount)} Ar</span></div>` : ''}
      <div class="row net"><span>Net à payer</span><span>${formatTicketAmount(data.total)} Ar</span></div>
      ${paymentRows}
      ${has(data.changeAmount) ? `<div class="row"><span>Rendu :</span><span>${formatTicketAmount(data.changeAmount)} Ar</span></div>` : ''}
    </div>
    ${has(data.beeper) ? `<div class="beeper">Beeper ${escapeHtml(data.beeper)}</div>` : ''}
  `
}

export function buildReceiptHTML(data = {}) {
  return `<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>${RECEIPT_STYLES}</style></head>
<body>${buildReceiptBody(data)}</body>
</html>`
}
