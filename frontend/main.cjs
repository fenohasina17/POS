const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');

let mainWindow;

function createWindow() {
  const preloadPath = path.join(__dirname, 'preload.cjs');
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    webPreferences: {
      preload: preloadPath,
      contextIsolation: true,
      nodeIntegration: false
    }
  });

  if (!app.isPackaged) {
    mainWindow.loadURL('http://localhost:5173');
    mainWindow.webContents.openDevTools();
  } else {
    mainWindow.loadFile(path.join(__dirname, 'dist/index.html'));
  }
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

// Styles pour 80mm compacts
function getPrintStyles() {
  return `
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      @page {
        size: 80mm auto;
        margin: 0mm;
      }
      body {
        font-family: Arial, Helvetica, sans-serif;
        width: 100%;
        max-width: 72mm;
        padding: 1.5mm 1mm;
        font-size: 10px;   /* Augmenté de 9px à 10px */
        line-height: 1.2;
        font-weight: 700;
      }
      .header {
        text-align: center;
        margin-bottom: 4px;
      }
      .company-name {
        font-size: 13px;  /* Augmenté pour visibilité */
        font-weight: 900;
        text-transform: uppercase;
        margin-bottom: 2px;
      }
      .divider {
        border-top: 1.5px dashed #000;
        margin: 4px 0;
        width: 100%;
      }
      .double-divider {
        border-top: 2px solid #000;
        margin: 4px 0;
        width: 100%;
      }
      .ticket-title {
        text-align: center;
        font-weight: 900;
        font-size: 12px;
        margin: 4px 0;
        text-transform: uppercase;
      }
      .info-line {
        display: flex;
        justify-content: space-between;
        margin: 1px 0;
        font-size: 10px;
        width: 100%;
      }
      .table {
        width: 100%;
        border-collapse: collapse;
        margin: 4px 0;
        table-layout: fixed;
      }
      .table th, .table td {
        padding: 2px 1px;
        font-size: 11px; /* Articles plus lisibles */
        word-break: break-word;
      }
      .table th {
        border-bottom: 1.5px dashed #000;
        text-transform: uppercase;
      }

      .text-left { text-align: left; }
      .text-center { text-align: center; }
      .text-right { text-align: right; }

      .grand-total {
        display: flex;
        justify-content: space-between;
        border-top: 2px solid #000;
        padding-top: 4px;
        margin-top: 4px;
        font-size: 14px; /* Total bien visible */
        width: 100%;
      }
      .footer {
        text-align: center;
        margin-top: 8px;
        font-size: 9px;
      }
      .payment-method {
        margin: 1px 0;
        display: flex;
        justify-content: space-between;
        font-size: 10px;
      }
    </style>`;
}

function generateHTML(arg, type) {
  const styles = getPrintStyles();

  if (type === 'facture') {
    const subtotal = arg.subtotal || arg.items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
    const total = arg.total || subtotal - (arg.discount || 0);

    return `<!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      ${styles}
    </head>
    <body>
      <div class="header">
        <div class="company-name">${arg.companyName || 'GASTRONOMY PIZZA'}</div>
        <div>${arg.address || 'Antananarivo, Madagascar'}</div>
      </div>
      <div class="divider"></div>
      <div class="ticket-title">FACTURE</div>
      <div class="info-line">
        <span>N°: ${arg.number || `FACT-${Date.now()}`}</span>
        <span>${arg.date || new Date().toLocaleString('fr-FR')}</span>
      </div>
      ${arg.client ? `<div class="info-line"><span>Client:</span><span>${arg.client}</span></div>` : ''}
      <div class="divider"></div>

      <table class="table">
        <thead>
          <tr>
            <th class="text-center" style="width: 15%">Qté</th>
            <th class="text-left" style="width: 45%">Article</th>
            <th class="text-right" style="width: 20%">P.U.</th>
            <th class="text-right" style="width: 20%">Total</th>
          </tr>
        </thead>
        <tbody>
          ${arg.items.map(item => `
            <tr>
              <td class="text-center">${item.quantity}</td>
              <td class="text-left">${item.name}</td>
              <td class="text-right">${(item.price || 0).toLocaleString()}</td>
              <td class="text-right">${((item.price || 0) * (item.quantity || 1)).toLocaleString()}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>

      <div class="divider"></div>

      <div class="info-line">
        <span>SOUS-TOTAL:</span>
        <span>${subtotal.toLocaleString()} Ar</span>
      </div>
      ${arg.discount ? `<div class="info-line"><span>REMISE:</span><span>-${arg.discount.toLocaleString()} Ar</span></div>` : ''}

      <div class="grand-total">
        <span>TOTAL</span>
        <span>${total.toLocaleString()} Ar</span>
      </div>

      ${arg.payments && arg.payments.length > 0 ? `
      <div class="divider"></div>
      ${arg.payments.map(p => `
        <div class="payment-method">
          <span>${p.method}:</span>
          <span>${(p.amount || 0).toLocaleString()} Ar</span>
        </div>
      `).join('')}
      ` : ''}

      <div class="footer">
        <div>MERCI DE VOTRE VISITE</div>
      </div>
    </body>
    </html>`;

  } else if (type === 'resume_session') {
    // Rapport Riche (Billetage)
    console.log('[DEBUG_PRINT] Données complètes reçues par Electron:', JSON.stringify(arg, null, 2));
    const cashSales = (arg.payments || []).find(p => p.payment_name === 'Espèce')?.total || 0;
    const variance = arg.actual_cash_amount - cashSales;

    return `<!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      ${styles}
    </head>
    <body>
      <div class="header">
        <div class="company-name">RECAPITULATIF SESSION</div>
        <div class="info-line"><span>Caisse:</span><span>${arg.cash_register_name}</span></div>
        <div class="info-line"><span>Caissier:</span><span>${arg.user_name}</span></div>
      </div>
      <div class="double-divider"></div>
      
      <div class="info-line"><span>Ouverture:</span><span>${new Date(arg.opened_at).toLocaleString('fr-FR')}</span></div>
      <div class="info-line"><span>Fond de caisse:</span><span>${(arg.starting_amount || 0).toLocaleString()} Ar</span></div>
      
      <div class="divider"></div>
      
      <!-- Produits par catégorie -->
      ${(arg.categories || []).map(cat => `
        <div style="margin-top: 5px;">
          <div style="font-size: 9px; font-weight: 900; border-bottom: 1px solid #000; margin-bottom: 2px;">${cat.category_name}</div>
          ${cat.products.map(p => `
            <div class="info-line">
              <span>${p.product_name} x${p.quantity}</span>
              <span>${(p.amount || 0).toLocaleString()}</span>
            </div>
          `).join('')}
        </div>
      `).join('')}
      
      <div class="divider"></div>
      <div class="ticket-title">DETAIL PAIEMENTS</div>
      ${(arg.payments || []).map(p => `
        <div class="info-line">
          <span>${p.payment_name}:</span>
          <span>${(p.total || 0).toLocaleString()} Ar</span>
        </div>
      `).join('')}
      
      <div class="double-divider"></div>
      
      <div class="info-line">
        <span>Ventes Espèces:</span>
        <span>${cashSales.toLocaleString()} Ar</span>
      </div>
      <div class="info-line">
        <span>Compté (Billetage):</span>
        <span>${(arg.actual_cash_amount || 0).toLocaleString()} Ar</span>
      </div>
      
      <div class="grand-total" style="color: ${variance >= 0 ? '#000' : '#000'};">
        <span>ECART</span>
        <span>${variance.toLocaleString()} Ar</span>
      </div>
      
      <div class="divider"></div>
      <div class="footer">
        <p>Gastronomie Pizza - Merci</p>
        <p>${new Date().toLocaleString('fr-FR')}</p>
      </div>
    </body>
    </html>`;

  } else {
    // Bon de préparation (Cuisine ou Bar)
    const titles = {
      kitchen: 'BON DE CUISINE',
      cook: 'BON DE CUISINE',
      bar: 'BON DE BAR'
    };
    const currentTitle = titles[arg.printerName] || 'BON DE PRÉPARATION';

    return `<!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      ${styles}
    </head>
    <body>
      <div class="header">
        <div class="company-name">${currentTitle}</div>
        <div class="info-line">
          <span>Table: ${arg.tableInfo?.name || 'N/A'}</span>
          <span>${new Date().toLocaleTimeString('fr-FR')}</span>
        </div>
        <div class="info-line">
          <span>Ticket: ${arg.tableInfo?.ticketNumber || 'N/A'}</span>
        </div>
      </div>
      <div class="double-divider"></div>

      <table class="table">
        <thead>
          <tr>
            <th class="text-center" style="width: 20%">Qté</th>
            <th class="text-left" style="width: 80%">Article</th>
          </tr>
        </thead>
        <tbody>
          ${arg.items.map(item => `
            <tr>
              <td class="text-center" style="font-size: 11px;">${item.quantity} x</td>
              <td class="text-left" style="font-size: 11px;">${item.name}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>

      <div class="footer">
        <div>--- À préparer ---</div>
      </div>
    </body>
    </html>`;
  }
}

// Fonction principale handlePrint avec gestion de fallback et XP-80C
async function handlePrint(arg, type) {
  const requestedPrinter = arg.printerName || 'receipt';
  let finalPrinterName = '';
  let printWindow = null;

  try {
    const printers = await mainWindow.webContents.getPrintersAsync();
    console.log('[PRINT] Imprimantes système détectées:', printers.map(p => p.name));

    // Logique pour identifier l'imprimante de la CAISSE (receipt)
    const findReceiptPrinter = () => {
      // 1. Chercher le nom exact "receipt"
      let p = printers.find(pr => pr.name === 'receipt');
      // 2. Sinon chercher par mots-clés (dont votre XP-80C)
      if (!p) {
        p = printers.find(pr =>
          pr.name.toLowerCase().includes('xp-80c') ||
          pr.name.toLowerCase().includes('thermal') ||
          pr.name.toLowerCase().includes('receipt') ||
          pr.name.toLowerCase().includes('pos')
        );
      }
      // 3. Sinon prendre l'imprimante par défaut Windows
      return p || printers.find(pr => pr.isDefault) || printers[0];
    };

    // Tentative de trouver l'imprimante demandée par son nom exact
    let target = printers.find(p => p.name === requestedPrinter);

    // GESTION DU REPLI (FALLBACK)
    if (!target) {
      console.warn(`[PRINT] Imprimante "${requestedPrinter}" introuvable.`);

      const isPrepPrinter = ['kitchen', 'bar', 'cook'].includes(requestedPrinter);

      if (isPrepPrinter) {
        console.log(`[PRINT] Redirection automatique du bon (${requestedPrinter}) vers "receipt"`);
        target = findReceiptPrinter();
      } else if (requestedPrinter === 'receipt') {
        target = findReceiptPrinter();
      }
    }

    if (!target) {
      return { success: false, message: 'Aucune imprimante disponible pour l\'impression.' };
    }

    finalPrinterName = target.name;
    console.log(`[PRINT] Envoi vers : "${finalPrinterName}" (Cible originale : "${requestedPrinter}")`);

    let htmlContent = generateHTML(arg, type);

    printWindow = new BrowserWindow({
      show: false,
      webPreferences: {
        nodeIntegration: false,
        printBackground: true
      }
    });

    await printWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(htmlContent)}`);
    await new Promise(resolve => setTimeout(resolve, 800));

    const printOptions = {
      silent: true,
      printBackground: true,
      deviceName: finalPrinterName,
      pageSize: {
        width: 226772,   // 80mm
        height: 400000
      }
    };

    await printWindow.webContents.print(printOptions);
    await new Promise(resolve => setTimeout(resolve, 1000));

    if (!printWindow.isDestroyed()) {
      printWindow.close();
    }

    return { success: true, message: `Impression réussie sur ${finalPrinterName}` };

  } catch (err) {
    console.error('[PRINT] Erreur critique:', err);
    if (printWindow && !printWindow.isDestroyed()) {
      printWindow.close();
    }
    return { success: false, message: err.message };
  }
}

// Handlers IPC
ipcMain.handle('print-receipt', (e, arg) => handlePrint(arg, 'facture'));
ipcMain.handle('print-order', (e, arg) => handlePrint(arg, 'bon_cuisine'));
ipcMain.handle('print-pdf-receipt', (e, arg) => handlePrint(arg, 'facture'));
ipcMain.handle('print-pdf-order', (e, arg) => handlePrint(arg, 'bon_cuisine'));
ipcMain.handle('print-session-summary', (e, arg) => handlePrint(arg, 'resume_session'));
ipcMain.handle('send-raw-commands', async (e, arg) => {
  console.log('[PRINT] Commandes brutes reçues (non supportées nativement via HTML print):', arg.commands);
  return { success: true, message: 'Commandes simulées' };
});
ipcMain.handle('generate-and-print-pdf-receipt', (e, arg) => handlePrint(arg, 'facture'));
ipcMain.handle('generate-pdf-receipt', (e, arg) => handlePrint(arg, 'facture'));
ipcMain.handle('generate-pdf-order', (e, arg) => handlePrint(arg, 'bon_cuisine'));

ipcMain.handle('get-printers', async () => {
  try {
    const printers = await mainWindow.webContents.getPrintersAsync();
    return printers.map(p => ({
      name: p.name,
      isDefault: p.isDefault,
      displayName: p.displayName
    }));
  } catch (err) {
    console.error('Erreur getPrinters:', err);
    return [];
  }
});

ipcMain.handle('open-file', async (event, filePath) => {
  try {
    const { shell } = require('electron');
    await shell.openPath(filePath);
    return { success: true };
  } catch (err) {
    return { success: false, message: err.message };
  }
});
