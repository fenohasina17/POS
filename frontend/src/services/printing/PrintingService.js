import { qzTrayAdapter } from './qzTrayAdapter'

/**
 * PrintingStrategy interface (Abstract Strategy)
 */
class PrintingStrategy {
  async print(data, printerName) {
    throw new Error('Method print() must be implemented')
  }
}

/**
 * Browser Print Strategy - Uses native window.print()
 */
class BrowserPrintStrategy extends PrintingStrategy {
  async print(data) {
    console.log('Printing via Browser:', data)
    window.print()
  }
}

/**
 * QZ Tray Strategy - Uses QZ Tray Adapter for ESC/POS or HTML
 */
class QZPrintStrategy extends PrintingStrategy {
  async print(data, printerName) {
    if (!printerName) {
      throw new Error('Printer name required for QZ Tray printing')
    }
    
    // If data is an array, assume it's raw commands
    const type = Array.isArray(data) ? 'raw' : 'html'
    
    console.log(`Printing via QZ Tray on ${printerName} (${type})`)
    return qzTrayAdapter.print(printerName, data)
  }
}

/**
 * PrintingService Facade (Singleton)
 * Coordinates the printing logic and strategy selection.
 */
class PrintingService {
  constructor() {
    this.strategies = {
      browser: new BrowserPrintStrategy(),
      qz: new QZPrintStrategy()
    }
    this.currentStrategy = 'browser'
  }

  /**
   * Helper to get the designated cash printer name
   */
  getCashPrinter() {
    return localStorage.getItem('cashPrinterName') || 'POS-80'
  }

  /**
   * Helper to get the designated kitchen fallback printer name
   */
  getKitchenFallbackPrinter() {
    return localStorage.getItem('kitchenPrinterName') || this.getCashPrinter()
  }

  setStrategy(type) {
    if (this.strategies[type]) {
      this.currentStrategy = type
    }
  }

  /**
   * Main print method for INVOICES - Always uses the Cash Printer
   * Direct printing via QZ Tray is prioritized to avoid browser dialog.
   */
  async printInvoice(invoiceData, useQzIfAvailable = true) {
    try {
      // Log preview in console for debugging (text format)
      const preview = qzTrayAdapter.generateTextPreview(invoiceData)
      console.log(preview)

      if (useQzIfAvailable && window.qz) {
        this.setStrategy('qz')
        const printer = this.getCashPrinter()
        
        // Convert invoice data to raw ESC/POS commands
        const commands = qzTrayAdapter.formatEscPosInvoice(invoiceData)
        
        console.log(`Tentative d'impression facture vers ${printer}...`)
        try {
          return await this.strategies.qz.print(commands, printer)
        } catch (err) {
          console.error(`Échec impression QZ vers ${printer} :`, err.message)
          return null
        }
      } else {
        console.warn('QZ Tray non disponible et impression navigateur désactivée.')
        return null
      }
    } catch (err) {
      console.error('PrintingService Error (Invoice) :', err)
      return null
    }
  }

  /**
   * Print kitchen order slips, grouped by the printer assigned to each category
   */
  async printOrder(tableInfo, items) {
    if (!window.qz) {
      console.warn('QZ Tray non disponible, repli sur console log')
      console.table(items)
      return
    }

    this.setStrategy('qz')
    const fallbackPrinter = this.getKitchenFallbackPrinter()

    // Mappage des types logiques vers les noms d'imprimantes réels détectés par QZ Tray
    const PRINTER_MAP = {
      'kitchen': 'kitchen',
      'bar': 'bar',
      'receipt': 'receipt',
      'pizza': 'pizza'
    };

    // Group items by printer defined in their category
    const printerGroups = {}

    items.forEach(item => {
      // Récupère le type (string) depuis la catégorie ou l'item
      let printerType = (item.printer?.name || item.printer) || 
                        (item.category?.printer?.name || item.category?.printer);

      // FALLBACK : Si aucune imprimante n'est définie, on utilise 'receipt' par défaut
      if (!printerType) {
        console.warn(`Aucune imprimante définie pour "${item.name}", utilisation de "receipt" par défaut.`);
        printerType = 'receipt';
      }

      if (!printerGroups[printerType]) {
        printerGroups[printerType] = []
      }
      printerGroups[printerType].push(item)
    })

    // Send a separate print job for each unique printer group found in categories
    const printPromises = Object.entries(printerGroups).map(async ([printerType, groupItems]) => {
      try {
        const printerName = PRINTER_MAP[printerType] || printerType;
        console.log(`Routing ${groupItems.length} items to printer: ${printerName} (type: ${printerType})`)
        const commands = qzTrayAdapter.formatEscPosOrder(tableInfo, groupItems, printerType)
        
        try {
          return await this.strategies.qz.print(commands, printerName)
        } catch (printErr) {
          console.warn(`Échec sur ${printerName}, tentative sur l'imprimante de secours : ${fallbackPrinter}`)
          return await this.strategies.qz.print(commands, fallbackPrinter)
        }
      } catch (err) {
        console.error(`Échec définitif d'impression pour le groupe ${printerType}:`, err)
      }
    })

    return Promise.all(printPromises)
    }

  /**
   * Print session summary
   */
  async printSessionSummary(summary) {
    if (!window.qz) {
      console.warn('QZ Tray non disponible')
      return
    }

    this.setStrategy('qz')
    const printer = this.getCashPrinter()
    const commands = qzTrayAdapter.formatEscPosSummary(summary)
    return this.strategies.qz.print(commands, printer)
  }

  /**
   * Send raw commands to a specific printer
   */
  async sendRawCommands(commands, printerName) {
    this.setStrategy('qz')
    return this.strategies.qz.print(commands, printerName || this.getCashPrinter())
  }
}

export const printingService = new PrintingService()
