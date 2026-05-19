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

// Styles pour 80mm
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
        max-width: 72mm; /* Ajusté pour laisser une marge physique standard d'imprimante */
        padding: 3mm 2mm; /* Marges internes optimisées pour éviter le débordement */
        font-size: 11px;  /* Taille globale diminuée pour compacter le ticket */
        line-height: 1.3;
        font-weight: 700;  /* Applique le GRAS par défaut à tout le document */
      }
      .header {
        text-align: center;
        margin-bottom: 8px;
      }
      .company-name {
        font-size: 15px;  /* Diminué mais reste distinct */
        font-weight: 900;  /* Ultra gras */
        text-transform: uppercase;
        margin-bottom: 4px;
        letter-spacing: 0.5px;
      }
      .divider {
        border-top: 2px dashed #000;
        margin: 8px 0;
        width: 100%;
      }
      .double-divider {
        border-top: 3px solid #000;
        margin: 8px 0;
        width: 100%;
      }
      .ticket-title {
        text-align: center;
        font-weight: 900;
        font-size: 13px;
        margin: 8px 0;
        text-transform: uppercase;
      }
      .info-line {
        display: flex;
        justify-content: space-between;
        margin: 3px 0;
        font-size: 11px;
        font-weight: 700; /* Forcé en gras */
        width: 100%;
      }
      .table {
        width: 100%;
        border-collapse: collapse;
        margin: 8px 0;
        table-layout: fixed;
      }
      .table th, .table td {
        padding: 4px 2px;
        font-size: 11px;  /* Alignement de taille uniforme */
        font-weight: 700;  /* Forcé en gras */
        word-break: break-word;
      }
      .table th {
        border-bottom: 2.5px dashed #000;
        font-weight: 900;  /* Plus épais pour les en-têtes */
        text-transform: uppercase;
      }

      /* Classes d'alignement pour le tableau */
      .text-left {
        text-align: left;
      }
      .text-center {
        text-align: center;
      }
      .text-right {
        text-align: right;
      }

      .grand-total {
        display: flex;
        justify-content: space-between;
        border-top: 3px solid #000;
        font-weight: 900;  /* Maximum de visibilité pour le prix */
        padding-top: 8px;
        margin-top: 8px;
        font-size: 14px;   /* Légèrement plus grand pour ressortir du lot */
        width: 100%;
      }
      .footer {
        text-align: center;
        margin-top: 15px;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.3;
      }
      .payment-method {
        margin: 3px 0;
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        font-weight: 700; /* Forcé en gras */
        width: 100%;
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
        <div>Tel: ${arg.phone || '034 00 000 00'}</div>
      </div>
      <div class="divider"></div>
      <div class="ticket-title">FACTURE</div>
      <div class="info-line">
        <span>Ticket N°:</span>
        <span>${arg.number || `FACT-${Date.now()}`}</span>
      </div>
      <div class="info-line">
        <span>Date:</span>
        <span>${arg.date || new Date().toLocaleString('fr-FR')}</span>
      </div>
      ${arg.client ? `<div class="info-line"><span>Client:</span><span>${arg.client}</span></div>` : ''}
      <div class="divider"></div>

      <table class="table">
        <thead>
          <tr>
            <th>Qté</th>
            <th>Article</th>
            <th class="text-right">P.U.</th>
            <th class="text-right">Total</th>
          </tr>
        </thead>
        <tbody>
          ${arg.items.map(item => `
            <tr>
              <td>${item.quantity}</td>
              <td>${item.name}</td>
              <td class="text-right">${(item.price || 0).toLocaleString()} Ar</td>
              <td class="text-right">${((item.price || 0) * (item.quantity || 1)).toLocaleString()} Ar</td>
            </tr>
          `).join('')}
        </tbody>
      </table>

      <div class="divider"></div>

      <div class="info-line">
        <span>SOUS-TOTAL:</span>
        <span>${subtotal.toLocaleString()} Ar</span>
      </div>
      ${arg.discount ? `
      <div class="info-line">
        <span>REMISE:</span>
        <span>-${arg.discount.toLocaleString()} Ar</span>
      </div>
      ` : ''}

      <div class="grand-total">
        <span>TOTAL À PAYER</span>
        <span>${total.toLocaleString()} Ar</span>
      </div>

      ${arg.payments && arg.payments.length > 0 ? `
      <div class="divider"></div>
      <div style="font-weight: bold; margin: 5px 0;">PAIEMENT</div>
      ${arg.payments.map(p => `
        <div class="payment-method">
          <span>${p.method}:</span>
          <span>${(p.amount || 0).toLocaleString()} Ar</span>
        </div>
      `).join('')}
      ` : ''}

      <div class="footer">
        <div>MERCI DE VOTRE VISITE</div>
        <div>A BIENTÔT !</div>
      </div>
    </body>
    </html>`;

  } else {
    // Bon de cuisine
    return `<!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      ${styles}
    </head>
    <body>
      <div class="header">
        <div class="company-name">BON DE CUISINE</div>
        <div class="info-line">
          <span>Table:</span>
          <span>${arg.tableInfo?.name || 'N/A'}</span>
        </div>
        <div class="info-line">
          <span>Ticket N°:</span>
          <span>${arg.tableInfo?.ticketNumber || 'N/A'}</span>
        </div>
        <div class="info-line">
          <span>Date:</span>
          <span>${new Date().toLocaleString('fr-FR')}</span>
        </div>
      </div>
      <div class="double-divider"></div>

      <table class="table">
        <thead>
          <tr>
            <th>Qté</th>
            <th>Article</th>
            <th class="text-right">Prix</th>
          </tr>
        </thead>
        <tbody>
          ${arg.items.map(item => `
            <tr>
              <td style="width: 15%">${item.quantity}x</td>
              <td style="width: 60%">${item.name}</td>
              <td class="text-right" style="width: 25%">${(item.price || 0).toLocaleString()} Ar</td>
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

// Fonction principale handlePrint corrigée pour 80mm
async function handlePrint(arg, type) {
  let printerName = arg.printerName || '';
  let printWindow = null;

  try {
    const printers = await mainWindow.webContents.getPrintersAsync();
    console.log('[PRINT] Imprimantes système:', printers.map(p => p.name));

    if (printerName === 'receipt' || !printerName) {
      let targetPrinter = printers.find(p => p.name === 'receipt');
      if (!targetPrinter) {
        targetPrinter = printers.find(p =>
          p.name.toLowerCase().includes('xprinter') ||
          p.name.toLowerCase().includes('thermal') ||
          p.name.toLowerCase().includes('receipt')
        );
      }
      if (!targetPrinter && printers.length > 0) {
        targetPrinter = printers[0];
      }
      if (targetPrinter) {
        printerName = targetPrinter.name;
        console.log(`[PRINT] Imprimante sélectionnée: "${printerName}"`);
      } else {
        return { success: false, message: 'Aucune imprimante trouvée' };
      }
    }

    let htmlContent = generateHTML(arg, type);

    printWindow = new BrowserWindow({
      show: false,
      webPreferences: {
        nodeIntegration: false,
        printBackground: true
      }
    });

    await printWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(htmlContent)}`);
    await new Promise(resolve => setTimeout(resolve, 1000));

    // OPTIONS POUR 80mm
    const printOptions = {
      silent: true,
      printBackground: true,
      deviceName: printerName,
      pageSize: {
        width: 226772,   // 80mm en points (80 * 2834.645 = 226771.6)
        height: 400000   // Hauteur max
      }
    };

    console.log(`[PRINT] Envoi à l'imprimante "${printerName}" avec format 80mm...`);
    await printWindow.webContents.print(printOptions);

    await new Promise(resolve => setTimeout(resolve, 1000));

    printWindow.close();
    console.log(`[PRINT] Impression réussie sur "${printerName}"`);
    return { success: true, message: `Impression sur ${printerName}` };

  } catch (err) {
    console.error('[PRINT] Erreur détaillée:', err);
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
