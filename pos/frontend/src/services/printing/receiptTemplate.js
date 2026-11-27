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

export const RECEIPT_STYLES = `
  * { margin: 0; padding: 0; box-sizing: border-box; color: #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  @page { size: 80mm auto; margin: 0; }
  html, body { background: #fff; }
  body { font-family: Arial, Helvetica, sans-serif; width: 100%; max-width: 72mm; padding: 2mm 1mm 4mm; font-size: 11px; line-height: 1.25; }
  .title { font-size: 15px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2px; margin-bottom: 3mm; }
  .head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2.5mm; }
  .logo { width: 22mm; height: auto; display: block; }
  .ticket { text-align: center; flex: 1; }
  .ticket-label { font-size: 12px; }
  .ticket-number { font-size: 26px; font-weight: 900; line-height: 1.05; }
  .pos { font-size: 12px; font-weight: 700; margin-bottom: 1mm; }
  .infos { display: flex; justify-content: space-between; gap: 2mm; margin-bottom: 3mm; }
  .infos .col { flex: 1; min-width: 0; }
  .infos .right { text-align: left; padding-left: 4mm; }
  .infos .label { font-weight: 700; }
  .infos div { word-break: break-word; }
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
 * @param {string} [data.serverName]      Serveur (vide si inconnu)
 * @param {string|Date} [data.date]
 * @param {string} [data.cashRegisterName]
 * @param {string} [data.serviceLabel]    « A emporter » / « Table 4 »
 * @param {Array<{method,amount}>} [data.payments]  Détail des règlements (une ligne chacun en bas)
 * @param {Array<{name,price,quantity}>} data.items
 * @param {number} [data.discount]        Montant de la remise (déjà calculé)
 * @param {number} data.total             Net à payer (déjà calculé)
 * @param {number|null} [data.amountReceived]  Utilisé seulement sans détail des paiements
 * @param {number|null} [data.changeAmount]
 * @param {string|number|null} [data.beeper]  Ligne masquée si absent
 */
export function buildReceiptBody(data = {}) {
  const items = Array.isArray(data.items) ? data.items : []
  const has = (v) => v !== null && v !== undefined && v !== ''

  const rows = items
    .map((item) => {
      const qty = Number(item.quantity) || 0
      const price = Number(item.price) || 0
      return `<tr>
        <td>${escapeHtml(item.name)}</td>
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
        <div class="label">Serveur :</div>
        <div>${escapeHtml(data.serverName ?? '')}</div>
      </div>
      <div class="col right">
        <div>${escapeHtml(formatTicketDate(data.date))}</div>
        ${has(data.cashRegisterName) ? `<div>${escapeHtml(data.cashRegisterName)}</div>` : ''}
        ${has(data.serviceLabel) ? `<div>${escapeHtml(data.serviceLabel)}</div>` : ''}
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
