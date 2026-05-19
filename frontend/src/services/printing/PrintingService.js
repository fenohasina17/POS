/**
 * PrintingService - Gestion des impressions et PDF (VERSION SANS LOCALSTORAGE)
 */
class PrintingService {
  constructor() {
    this.detectEnvironment();
  }

  detectEnvironment() {
    const isElectron = !!(window && window.electronAPI);
    this.isElectron = isElectron;
    console.log(`[DEBUG_PRINT] Environnement détecté : ${isElectron ? 'Electron' : 'Navigateur classique (Web)'}`);
  }

  /**
   * 1. LA FACTURE CLIENT : Envoi à la caisse principale ('receipt' par défaut)
   */
  async printInvoicePDF(invoiceData) {
    if (!this.isElectron || !window.electronAPI) {
      console.error('[DEBUG_PRINT] Electron API indisponible pour printInvoicePDF');
      return { success: false, message: 'Electron API not available' };
    }

    try {
      const subtotal = invoiceData.subtotal || invoiceData.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
      const total = invoiceData.total || subtotal - (invoiceData.discount || 0);

      const printData = {
        printerName: 'receipt', // Caisse par défaut
        companyName: invoiceData.companyName || 'GASTRONOMY PIZZA',
        address: invoiceData.address || 'Antananarivo, Madagascar',
        phone: invoiceData.phone || '034 00 000 00',
        number: invoiceData.number || `FACT-${Date.now()}`,
        date: invoiceData.date || new Date().toLocaleString('fr-FR'),
        items: invoiceData.items.map(item => ({
          name: item.name,
          quantity: Number(item.quantity) || 1,
          price: Number(item.price) || 0,
          total: (Number(item.quantity) || 1) * (Number(item.price) || 0)
        })),
        subtotal: subtotal,
        discount: invoiceData.discount || 0,
        total: total,
        client: invoiceData.client || '',
        payments: invoiceData.payments || [{ method: 'Espèces', amount: total }]
      };

      console.log(`[DEBUG_PRINT] Envoi de la facture finale à la caisse (receipt)`);
      return await window.electronAPI.printPDFReceipt(printData);
    } catch (err) {
      console.error('[DEBUG_PRINT] Erreur impression PDF Facture :', err);
      return { success: false, message: err.message };
    }
  }

  /**
   * 2. LES BONS DE PREPARATION (PDF) : Reçoit l'imprimante cible directe depuis InvoiceModal
   */
  async printOrderPDF(tableInfo, items, printerName = 'receipt') {
    if (!this.isElectron || !window.electronAPI) {
      return { success: false, message: 'Electron API not available' };
    }
    try {
      // Si printerName est absent ou invalide, on se replie sur l'imprimante générale 'receipt'
      const finalPrinter = printerName || 'receipt';
      console.log(`[DEBUG_PRINT] Transmission du bon de cuisine à Electron vers : "${finalPrinter}"`);

      return await window.electronAPI.printPDFOrder({
        printerName: finalPrinter,
        savePDF: false,
        tableInfo: { name: tableInfo.name, ticketNumber: tableInfo.ticketNumber },
        items: items.map(item => ({ name: item.name, quantity: item.quantity, price: item.price }))
      });
    } catch (err) {
      console.error('[DEBUG_PRINT] Erreur dans printOrderPDF :', err);
      return { success: false, message: err.message };
    }
  }

  /**
   * Impression classique/brute du reçu caisse
   */
  async printInvoice(invoiceData) {
    if (!this.isElectron || !window.electronAPI) {
      return { success: false, message: 'Electron API not available' };
    }
    try {
      const printData = {
        companyName: invoiceData.companyName || 'GASTRONOMY PIZZA',
        address: invoiceData.address || 'Antananarivo, Madagascar',
        phone: invoiceData.phone || '034 00 000 00',
        number: invoiceData.number || `FACT-${Date.now()}`,
        date: invoiceData.date || new Date().toLocaleString('fr-FR'),
        items: invoiceData.items.map(item => ({ name: item.name, quantity: item.quantity, price: item.price, total: item.price * item.quantity })),
        subtotal: invoiceData.subtotal || invoiceData.items.reduce((sum, item) => sum + (item.price * item.quantity), 0),
        discount: invoiceData.discount || 0,
        total: invoiceData.total || invoiceData.items.reduce((sum, item) => sum + (item.price * item.quantity), 0),
        client: invoiceData.client || '',
        payments: invoiceData.payments || [{ method: 'Espèces', amount: invoiceData.total || 0 }],
        printerName: 'receipt'
      };

      return await window.electronAPI.printReceipt(printData);
    } catch (err) {
      return { success: false, message: err.message };
    }
  }

  /**
   * Tri et impression brute des bons de cuisine (sans PDF)
   */
  async printOrder(tableInfo, items) {
    if (!this.isElectron || !window.electronAPI) {
      return [{ success: false, message: 'Electron API not available' }];
    }

    const printerGroups = {};

    items.forEach((item) => {
      const printerName = item.printer?.name || item.printer || 'receipt';
      if (!printerGroups[printerName]) {
        printerGroups[printerName] = [];
      }
      printerGroups[printerName].push({
        name: item.name,
        quantity: item.quantity,
        price: item.price
      });
    });

    const printPromises = Object.entries(printerGroups).map(async ([printerName, groupItems]) => {
      try {
        return await window.electronAPI.printOrder({
          printerName: printerName,
          tableInfo: { name: tableInfo.name, ticketNumber: tableInfo.ticketNumber },
          items: groupItems
        });
      } catch (err) {
        return { success: false, message: err.message };
      }
    });

    return Promise.all(printPromises);
  }

  async generateAndPrintInvoicePDF(invoiceData, savePDF = false) {
    if (!this.isElectron || !window.electronAPI) {
      return { success: false, message: 'Electron API not available', path: null };
    }
    try {
      const subtotal = invoiceData.subtotal || invoiceData.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
      const total = invoiceData.total || subtotal - (invoiceData.discount || 0);

      const printData = {
        printerName: 'receipt',
        savePDF: savePDF,
        companyName: invoiceData.companyName || 'GASTRONOMY PIZZA',
        address: invoiceData.address || 'Antananarivo, Madagascar',
        phone: invoiceData.phone || '034 00 000 00',
        number: invoiceData.number || `FACT-${Date.now()}`,
        date: invoiceData.date || new Date().toLocaleString('fr-FR'),
        items: invoiceData.items.map(item => ({
          name: item.name,
          quantity: Number(item.quantity) || 1,
          price: Number(item.price) || 0,
          total: (Number(item.quantity) || 1) * (Number(item.price) || 0)
        })),
        subtotal: subtotal,
        discount: invoiceData.discount || 0,
        total: total,
        client: invoiceData.client || '',
        payments: invoiceData.payments || [{ method: 'Espèces', amount: total }]
      };

      return await window.electronAPI.generateAndPrintPDFReceipt(printData);
    } catch (err) {
      console.error('[DEBUG_PRINT] Erreur génération/impression Reçu PDF:', err);
      return { success: false, message: err.message, path: null };
    }
  }

  async getAvailablePrinters() {
    if (this.isElectron && window.electronAPI) {
      try {
        return await window.electronAPI.getPrinters();
      } catch (err) {
        return [];
      }
    }
    return [];
  }
}

export const printingService = new PrintingService();
