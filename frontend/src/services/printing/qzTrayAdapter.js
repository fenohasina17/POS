/**
 * QZTrayAdapter provides a simplified interface to the QZ Tray library.
 * It follows the Adapter pattern to wrap QZ Tray's low-level API.
 */
class QZTrayAdapter {
  constructor() {
    this.qz = window.qz || null
    this.isConnected = false
  }

  async connect() {
    if (typeof window !== 'undefined' && window.qz) this.qz = window.qz
    if (!this.qz) return false
    if (this.isConnected && this.qz.websocket.isActive()) return true
    try {
      const certificate = `-----BEGIN CERTIFICATE-----
MIIECzCCAvOgAwIBAgIGAZ3O17YzMA0GCSqGSIb3DQEBCwUAMIGiMQswCQYDVQQG
EwJVUzELMAkGA1UECAwCTlkxEjAQBgNVBAcMCUNhbmFzdG90YTEbMBkGA1UECgwS
UVogSW5kdXN0cmllcywgTExDMRswGQYDVQQLDBJRWiBJbmR1c3RyaWVzLCBMTEMx
HDAaBgkqhkiG9w0BCQEWDXN1cHBvcnRAcXouaW8xGjAYBgNVBAMMEVFaIFRyYXkg
RGVtbyBDZXJ0MB4XDTI2MDQyNjEyMDg1NloXDTQ2MDQyNjEyMDg1NlowgaIxCzAJ
BgNVBAYTAlVTMQswCQYDVQQIDAJOWTESMBAGA1UEBwwJQ2FuYXN0b3RhMRswGQYD
VQQKDBJRWiBJbmR1c3RyaWVzLCBMTEMxGzAZBgNVBAsMElFaIEluZHVzdHJpZXMs
IExMQzEcMBoGCSqGSIb3DQEJARYNc3VwcG9ydEBxei5pbzEaMBgGA1UEAwwRUVog
VHJheSBEZW1vIENlcnQwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCg
cJZxEhunhndU0c6p596+nfOT+EiCxW+yZbUNceOHqAgsPbg9ABaU//nMYV1FdPOt
JEHFGy/DFptcV/9JPaTzimF4B4sMfDOUYKqa0Pk+4zOZ9eCrGBleWbPcNpl3FNyY
dlzYStIf8S/T4tyYnJBMpl5DYnsrIVxSnaTFi3R+/gkGUxJ5ebivrqm4+0rKBnfp
s23SYsWvQrnndlg7MjwmQkaRMDKB0JekkK0q97zWQdti4HBpkGyX/ptFO2hFVqju
uUUxQUFvvJu2yt/vwsJwNNZAleuxbxZsEwE1C+Rz0pVlVDguBt6GRBW3of6L672g
bKgdCmj57QRosYJwJCo5AgMBAAGjRTBDMBIGA1UdEwEB/wQIMAYBAf8CAQEwDgYD
VR0PAQH/BAQDAgEGMB0GA1UdDgQWBBQ6vN0DK85yPcxVoXV8QChjf07Q0DANBgkq
hkiG9w0BAQsFAAOCAQEATlol3QOJXYbbZQA26+VAXKmvCwCZgSvwEl6eIRFeBdQH
fZCtEeyMT8u5bNcjabWqx+WP/TFIbrJaReRBYFYplzBDFBb/ku6OcYGIgmfCoPAq
ksVXjGUd8ZW3NWwik2BmQM03D06tkRzosxeMAb7TRl/WyqaXbHCQJCBBr47l2msh
04ZJ0f//5BnDJE44Lb6JervgyYor18fD0evFIYceldUooJR05ABRidJPrzuMAYfJ
DCVMaHhhYO74kVoqwte/RDfqOFk2DMDjwrFdMyWtD08o7ChBadLTY0CtDRvchNVm
makQs/IEobtzvHqzc0ugF3wMKEGKoMiyRgPHJQmzyQ==
-----END CERTIFICATE-----`;
      this.qz.security.setCertificatePromise((resolve) => resolve(certificate));
      this.qz.security.setSignaturePromise((toSign) => (resolve, reject) => {
        fetch('http://127.0.0.1:8000/api/qz/sign', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ request: toSign }) })
          .then(res => res.text()).then(resolve).catch(reject);
      });
      await this.qz.websocket.connect({ timeout: 1000 })
      this.isConnected = true
      return true
    } catch (err) { this.isConnected = false; return false }
  }

  async findPrinter(name) {
    await this.connect()
    if (name) { try { return await window.qz.printers.find(name) } catch (err) { console.warn(`Imprimante "${name}" non trouvée`) } }
    try { return await window.qz.printers.getDefault() } catch (err) { throw new Error("Aucune imprimante configurée.") }
  }

  async print(printerName, data, options = {}) {
    try {
      await this.connect()
      const resolvedPrinter = await this.findPrinter(printerName)
      const config = window.qz.configs.create(resolvedPrinter, options)
      await window.qz.print(config, data)
    } catch (err) { console.error('Printing failed', err); throw err }
  }

  formatLine(left, right, width = 32) {
    const spaceCount = width - (left.length + right.length)
    return spaceCount > 0 ? left + ' '.repeat(spaceCount) + right + '\n' : left.substring(0, width - right.length - 1) + ' ' + right + '\n'
  }

  formatEscPosOrder(tableInfo, items, printerType = 'kitchen') {
    const width = 32
    const titles = { 'kitchen': 'COMMANDE CUISINE', 'bar': 'COMMANDE BAR', 'receipt': 'COMMANDE CAISSE', 'pizza': 'COMMANDE PIZZA' }
    let commands = ['\x1B\x40', '\x1B\x61\x01', '\x1B\x21\x10', (titles[printerType] || 'COMMANDE') + '\n', '\x1B\x21\x00', '--------------------------------\n', '\x1B\x61\x00', 'Table: ' + (tableInfo.name || 'N/A') + '\n', (tableInfo.ticketNumber ? 'Ticket: ' + tableInfo.ticketNumber + '\n' : ''), 'Heure: ' + new Date().toLocaleTimeString() + '\n', '--------------------------------\n']
    items.forEach(item => commands.push(item.quantity + ' x ' + item.name.toUpperCase() + '\n'))
    commands.push('--------------------------------\n', '\n\n\n', '\x1D\x56\x41\x03')
    return commands
  }

  formatEscPosInvoice(invoice) {
    const width = 40
    let commands = ['\x1B\x40', '\x1B\x61\x01', '\x1B\x45\x01', '\x1B\x21\x10', invoice.companyName + '\n', '\x1B\x21\x00', '\x1B\x45\x00', invoice.address + '\n', '----------------------------------------\n', '\x1B\x61\x00', 'Ticket: ' + invoice.number + '\n', 'Date:   ' + invoice.date + '\n', 'Client: ' + (invoice.client || 'Passant') + '\n', '----------------------------------------\n']
    invoice.items.forEach(item => commands.push(this.formatLine(`${item.quantity}x ${item.name.toUpperCase().substring(0, 20)}`, `${Math.round(item.quantity * item.price)} Ar`, width) + '\n'))
    commands.push('----------------------------------------\n', '\x1B\x61\x02', '\x1B\x21\x10', '\x1B\x45\x01', 'TOTAL: ' + invoice.total + ' Ar\n', '\x1B\x45\x00', '\x1B\x21\x00', '\n\n', '\x1B\x61\x01', 'Merci de votre visite!\n', 'A bientot\n\n\n', '\x1D\x56\x41\x03')
    return commands
  }

  generateTextPreview(invoice) {
    const width = 32
    let text = '=== APERÇU TICKET ===\n\n' + invoice.companyName.padStart((width + invoice.companyName.length) / 2).toUpperCase() + '\n' + invoice.address.padStart((width + invoice.address.length) / 2) + '\n' + '-'.repeat(width) + '\n' + `Ticket: ${invoice.number}\n` + `Date:   ${invoice.date}\n` + `Client: ${invoice.client || 'Passant'}\n` + '-'.repeat(width) + '\n'
    invoice.items.forEach(item => {
      text += item.name.toUpperCase() + '\n'
      const qtyPrice = `  ${item.quantity} x ${Math.round(item.price)}`, lineTotal = `${Math.round(item.quantity * item.price)}`
      text += qtyPrice + ' '.repeat(Math.max(0, width - (qtyPrice.length + lineTotal.length))) + lineTotal + '\n'
    })
    text += '-'.repeat(width) + '\n' + `TOTAL: ${Math.round(invoice.total).toLocaleString('fr-FR')} Ar`.padStart(width) + '\n\n' + 'Merci de votre visite!\n'.padStart((width + 22) / 2) + 'A bientot\n'.padStart((width + 10) / 2) + '\n\n=== FIN DU TICKET ==='
    return text
  }

  formatEscPosSummary(summary) {
    const width = 32
    let commands = ['\x1B\x40', '\x1B\x61\x01', '\x1B\x21\x10', 'RECAPITULATIF SESSION\n', '\x1B\x21\x00', '--------------------------------\n', '\x1B\x61\x00', 'Ouverture: ' + new Date(summary.session.opened_at).toLocaleString('fr-FR') + '\n', 'Cloture:   ' + new Date(summary.session.closed_at).toLocaleString('fr-FR') + '\n', 'Caissier:  ' + (summary.session.user?.name || 'Inconnu') + '\n', '--------------------------------\n']
    
    // 1. Paiements
    commands.push('DETAILS PAIEMENTS:\n')
    if (summary.payments && Array.isArray(summary.payments)) {
      summary.payments.forEach(p => {
        if (Number(p.total) > 0) commands.push(this.formatLine(p.payment_name, `${p.total}`, width))
      })
    }
    
    // 2. Écart
    if (summary.admin_finance) {
      const finance = summary.admin_finance
      commands.push('--------------------------------\n')
      commands.push(this.formatLine('Cash attendu:', `${finance.expected_cash_amount || 0}`, width))
      commands.push(this.formatLine('Cash compté:', `${finance.actual_cash_counted || 0}`, width))
      commands.push('--------------------------------\n')
      commands.push(this.formatLine('ECART:', `${finance.cash_difference || 0}`, width))
    }
    commands.push('--------------------------------\n')

    // 3. Produits
    commands.push('PRODUITS PAR CATEGORIE:\n')
    if (summary.categories && Array.isArray(summary.categories)) {
      summary.categories.forEach(cat => {
        commands.push('\x1B\x45\x01' + cat.category_name.toUpperCase() + '\n' + '\x1B\x45\x00')
        if (cat.products) {
          cat.products.forEach(p => {
            const label = `  ${p.product_name.substring(0, 14)} x${p.quantity}`
            commands.push(this.formatLine(label, `${p.amount}`, width))
          })
        }
      })
    }
    commands.push('\n\n\x1B\x61\x01' + 'Merci de votre confiance\n\n\n')
    return commands
  }
}

export const qzTrayAdapter = new QZTrayAdapter()
