// import { qzTrayAdapter } from './qzTrayAdapter' // Removed QZ Tray import

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
    // NOTE: This is a placeholder. Browser printing is not suitable for ESC/POS.
    // For actual browser printing, more implementation would be needed here.
    // For now, it will just log to the console.
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
    }
    this.currentStrategy = 'browser' // Default to browser strategy
  }

  /**
   * Helper to get the designated cash printer name
   */
  getCashPrinter() {
    return localStorage.getItem('cashPrinterName') || 'receipt'
  }

  /**
   * Helper to get the designated kitchen fallback printer name
   */
  getKitchenFallbackPrinter() {
    // If no kitchen printer is defined, we ALWAYS fallback to the cash printer
    return localStorage.getItem('kitchenPrinterName') || this.getCashPrinter()
  }

  /**
   * Formats raw data into the structure expected by electron-pos-printer
   */
  formatInvoiceForElectron(data) {
    const items = [
      {
        type: 'text',
        value: data.companyName || 'GASTRONOMY PIZZA',
        style: { fontWeight: 'bold', textAlign: 'center', fontSize: '18px' }
      },
      {
        type: 'text',
        value: data.address || '',
        style: { textAlign: 'center', fontSize: '12px' }
      },
      { type: 'text', value: '--------------------------------', style: { textAlign: 'center' } },
      {
        type: 'text',
        value: `Ticket: ${data.number}`,
        style: { textAlign: 'left', fontSize: '12px' }
      },
      {
        type: 'text',
        value: `Date: ${data.date}`,
        style: { textAlign: 'left', fontSize: '12px' }
      },
      { type: 'text', value: '--------------------------------', style: { textAlign: 'center' } }
    ];

    // Table header
    items.push({
      type: 'table-header',
      value: { qte: 'Qte', name: 'Désignation', total: 'Total' },
      style: { fontWeight: 'bold', fontSize: '12px' }
    });

    // Items
    data.items.forEach(item => {
      items.push({
        type: 'table-row',
        value: {
          qte: item.quantity.toString(),
          name: item.name.substring(0, 18),
          total: (item.price * item.quantity).toLocaleString()
        },
        style: { fontSize: '12px' }
      });

      // Ligne optionnelle pour les notes ou détails
      if (item.quantity > 1) {
        items.push({
          type: 'table-row',
          value: {
            qte: '',
            name: `  (× ${item.price.toLocaleString()} Ar)`,
            total: ''
          },
          style: { fontSize: '10px', fontStyle: 'italic' }
        });
      }
    });

    items.push({ type: 'text', value: '--------------------------------', style: { textAlign: 'center' } });

    items.push({
      type: 'text',
      value: `TOTAL: ${data.total.toLocaleString()} Ar`,
      style: { textAlign: 'right', fontSize: '16px', fontWeight: 'bold' }
    });

    if (data.client) {
      items.push({
        type: 'text',
        value: `Client: ${data.client}`,
        style: { textAlign: 'left', fontSize: '12px' }
      });
    }

    items.push({
      type: 'text',
      value: '\nMERCI DE VOTRE VISITE\nA BIENTOT !',
      style: { textAlign: 'center', fontSize: '12px' }
    });

    return items;
  }

  formatOrderForElectron(printerName, tableInfo, items) {
    const printData = [
      {
        type: 'text',
        value: `${printerName.toUpperCase()}`,
        style: { fontWeight: 'bold', textAlign: 'center', fontSize: '20px' }
      },
      {
        type: 'text',
        value: `TABLE: ${tableInfo.name}`,
        style: { textAlign: 'center', fontSize: '14px' }
      },
      {
        type: 'text',
        value: `Ticket: ${tableInfo.ticketNumber || '-'}`,
        style: { textAlign: 'center', fontSize: '12px' }
      },
      {
        type: 'text',
        value: `Heure: ${new Date().toLocaleTimeString('fr-FR')}`,
        style: { textAlign: 'center', fontSize: '12px' }
      },
      { type: 'text', value: '================================', style: { textAlign: 'center' } }
    ];

    items.forEach(item => {
      printData.push({
        type: 'table-row',
        value: { 
          qte: item.quantity.toString(), 
          name: item.name, 
          total: '' 
        },
        style: { fontSize: '16px', fontWeight: 'bold' }
      });
    });

    printData.push({ type: 'text', value: '================================', style: { textAlign: 'center' } });
    return printData;
  }

  setStrategy(type) {
    if (this.strategies[type]) {
      this.currentStrategy = type
    } else {
      this.currentStrategy = 'browser';
    }
  }

  /**
   * Main print method for INVOICES - Uses the Cash Printer via IPC.
   */
  async printInvoice(invoiceData) {
    // DEBUG DEEP
    console.log('--- ENV DEBUG ---');
    console.log('User Agent:', navigator.userAgent);
    console.log('Is Electron API present?', !!window.electronAPI);
    console.log('Window keys:', Object.keys(window).filter(k => k.toLowerCase().includes('electron')));

    if (typeof window === 'undefined' || !window.electronAPI) {
      if (navigator.userAgent.includes('Electron')) {
        console.error('CRITICAL: You ARE in Electron, but preload.cjs FAILED to inject the API!');
      } else {
        console.error('You are NOT in Electron. You are in a normal browser.');
      }
      return { success: false, message: 'Interface Electron non détectée.' };
    }

    try {
      const printerName = this.getCashPrinter()
      const formattedData = this.formatInvoiceForElectron(invoiceData)

      console.log(`Attempting to print invoice to ${printerName} via IPC...`);

      const result = await window.electronAPI.printReceipt({
        data: formattedData,
        options: {
          printerName: printerName,
          pageSize: '80mm',
        }
      });

      return result;
    } catch (err) {
      console.error('PrintingService Error (Invoice IPC) :', err);
      return { success: false, message: err.message || 'An IPC error occurred' };
    }
  }

  /**
   * Print kitchen order slips, grouped by the printer assigned to each category.
   */
  async printOrder(tableInfo, items) {
    if (typeof window === 'undefined' || !window.electronAPI) {
      console.error('Electron API not available for printOrder.');
      return [{ success: false, message: 'Interface Electron non détectée.' }];
    }

    // Group items by printer defined in their category
    const printerGroups = {}

    items.forEach(item => {
      let printerName = (item.printer?.name || item.printer) ||
                        (item.category?.printer?.name || item.category?.printer);

      if (!printerName) {
        printerName = this.getKitchenFallbackPrinter();
      }

      if (!printerGroups[printerName]) {
        printerGroups[printerName] = []
      }
      printerGroups[printerName].push(item)
    })

    const printPromises = Object.entries(printerGroups).map(async ([printerName, groupItems]) => {
      try {
        const formattedItems = this.formatOrderForElectron(printerName, tableInfo, groupItems);
        const result = await window.electronAPI.printOrder({
          items: formattedItems,
          printerName: printerName
        });

        return result;
      } catch (err) {
        console.error(`IPC Error for printer ${printerName}:`, err);
        return { success: false, message: err.message || 'An IPC error occurred' };
      }
    })

    return Promise.all(printPromises)
  }

  /**
   * Print a summary of a cash register session.
   */
  async printSessionSummary(summaryData) {
    if (typeof window === 'undefined' || !window.electronAPI) {
      console.error('Electron API not available for printSessionSummary.');
      return { success: false, message: 'Interface Electron non détectée.' };
    }

    try {
      const printerName = this.getCashPrinter();
      const printData = [
        {
          type: 'text',
          value: 'RÉCAPITULATIF SESSION',
          style: { fontWeight: 'bold', textAlign: 'center', fontSize: '18px' }
        },
        {
          type: 'text',
          value: `Session #${summaryData.session?.id || '-'}`,
          style: { textAlign: 'center', fontSize: '12px' }
        },
        {
          type: 'text',
          value: `Caisse: ${summaryData.session?.cash_register?.name || '-'}`,
          style: { textAlign: 'center', fontSize: '12px' }
        },
        { type: 'text', value: '--------------------------------', style: { textAlign: 'center' } }
      ];

      // Summary details
      printData.push({ type: 'text', value: `Ouverture: ${summaryData.session?.opened_at || '-'}`, style: { fontSize: '12px' } });
      printData.push({ type: 'text', value: `Fond de caisse: ${Number(summaryData.session?.starting_amount || 0).toLocaleString()} Ar`, style: { fontSize: '12px' } });
      printData.push({ type: 'text', value: '--------------------------------', style: { textAlign: 'center' } });

      // Categories
      summaryData.categories?.forEach(cat => {
        printData.push({ type: 'text', value: cat.category_name, style: { fontWeight: 'bold', fontSize: '12px', borderBottom: '1px solid black' } });
        cat.items?.forEach(item => {
          printData.push({
            type: 'text',
            value: `${item.quantity} x ${item.name.substring(0, 15)}: ${Number(item.total).toLocaleString()} Ar`,
            style: { fontSize: '11px' }
          });
        });
      });

      printData.push({ type: 'text', value: '--------------------------------', style: { textAlign: 'center' } });

      // Payments
      printData.push({ type: 'text', value: 'PAIEMENTS:', style: { fontWeight: 'bold', fontSize: '12px' } });
      summaryData.payments?.forEach(pay => {
        printData.push({
          type: 'text',
          value: `${pay.payment_name}: ${Number(pay.total).toLocaleString()} Ar`,
          style: { fontSize: '12px' }
        });
      });

      printData.push({ type: 'text', value: '--------------------------------', style: { textAlign: 'center' } });
      printData.push({
        type: 'text',
        value: `CA TOTAL: ${Number(summaryData.total_sales || 0).toLocaleString()} Ar`,
        style: { fontWeight: 'bold', fontSize: '14px', textAlign: 'right' }
      });

      const result = await window.electronAPI.printReceipt({
        data: printData,
        options: {
          printerName: printerName,
          pageSize: '80mm'
        }
      });
      return result;
    } catch (err) {
      console.error('Error printing session summary:', err);
      return { success: false, message: err.message };
    }
  }

  async runLayoutTest() {
    const testData = {
      companyName: "TEST MISE EN PAGE",
      number: "000-TEST",
      date: new Date().toLocaleString('fr-FR'),
      items: [
        { name: "Pizza Margherita", quantity: 1, price: 15000 },
        { name: "Coca-Cola 33cl", quantity: 2, price: 3000 }
      ],
      total: 21000,
      client: "Client Test"
    };
    return await this.printInvoice(testData);
  }

  /**
   * Dummy method to avoid errors in components using raw commands.
   */
  async sendRawCommands(commands) {
    console.warn('sendRawCommands is not supported in Electron mode. Ignoring commands:', commands);
    return { success: true };
  }
}

export const printingService = new PrintingService()
