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
      /* Bon de préparation : « SUR PLACE » / « À EMPORTER » lisible d'un coup d'œil */
      .service-banner {
        margin: 8px 0 4px;
        padding: 4px 0;
        border: 3px solid #000;
        text-align: center;
        font-size: 22px;
        font-weight: 900;
        letter-spacing: 1px;
        width: 100%;
      }
      /* Ticket réparti sur plusieurs postes : les autres postes sont cités */
      .shared-order {
        margin: 6px 0 2px;
        padding: 4px;
        border: 4px double #000;
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        width: 100%;
      }
      .shared-order-title {
        font-size: 15px;
        font-weight: 900;
        letter-spacing: 1px;
      }
      .payment-method {
        margin: 1px 0;
        display: flex;
        justify-content: space-between;
        font-size: 10px;
      }
    </style>`;
}

// Ticket client : modèle partagé avec l'aperçu de l'interface (src/services/printing/receiptTemplate.js)
let receiptTemplatePromise = null;
function loadReceiptTemplate() {
  if (!receiptTemplatePromise) {
    const { pathToFileURL } = require('url');
    const file = path.join(__dirname, 'src', 'services', 'printing', 'receiptTemplate.js');
    receiptTemplatePromise = import(pathToFileURL(file).href);
  }
  return receiptTemplatePromise;
}

function generateHTML(arg, type) {
  const styles = getPrintStyles();

  if (type === 'resume_session') {
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
      fastfood: 'BON FAST FOOD',
      pizza: 'BON PIZZA',
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

      ${arg.otherStations ? `
      <div class="shared-order">
        <div class="shared-order-title">SUITE DE COMMANDE</div>
        <div>Aussi en : ${arg.otherStations}</div>
      </div>` : ''}

      ${(arg.serviceSections || [{ banner: '', items: arg.items }]).map(section => `
      ${section.banner ? `<div class="service-banner">${section.banner}</div>` : ''}
      <table class="table">
        <thead>
          <tr>
            <th class="text-center" style="width: 20%">Qté</th>
            <th class="text-left" style="width: 80%">Article</th>
          </tr>
        </thead>
        <tbody>
          ${section.items.map(item => `
            <tr>
              <td class="text-center" style="font-size: 11px;">${item.quantity} x</td>
              <td class="text-left" style="font-size: 11px;">${item.name}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
      `).join('')}

      <div class="footer">
        <div>--- À préparer ---</div>
      </div>
    </body>
    </html>`;
  }
}

// ── Routage des imprimantes (réglé par caisse, stocké avec les données de l'appli) ──
let printerRoutingPromise = null;
function loadPrinterRouting() {
  if (!printerRoutingPromise) {
    const { pathToFileURL } = require('url');
    const file = path.join(__dirname, 'src', 'services', 'printing', 'printerRouting.js');
    printerRoutingPromise = import(pathToFileURL(file).href);
  }
  return printerRoutingPromise;
}

const printerRoutingFile = () => path.join(app.getPath('userData'), 'printer-routing.json');

function readPrinterRouting() {
  try {
    return JSON.parse(require('fs').readFileSync(printerRoutingFile(), 'utf8')) || {};
  } catch {
    return {}; // pas encore réglé : comportement par défaut
  }
}

ipcMain.handle('get-printer-routing', () => readPrinterRouting());

ipcMain.handle('set-printer-routing', async (e, routing) => {
  const { normalizeRouting } = await loadPrinterRouting();
  const clean = normalizeRouting(routing);
  require('fs').writeFileSync(printerRoutingFile(), JSON.stringify(clean, null, 2));
  return clean;
});

// Fonction principale handlePrint avec gestion de fallback et XP-80C
async function handlePrint(arg, type) {
  const requestedPrinter = arg.printerName || 'receipt';
  let finalPrinterName = '';
  let printWindow = null;

  try {
    const printers = await mainWindow.webContents.getPrintersAsync();
    console.log('[PRINT] Imprimantes système détectées:', printers.map(p => p.name));

    // Poste -> imprimante : réglage de cette caisse (écran « Routage des imprimantes »),
    // sinon imprimante portant le nom du poste, sinon replis cuisine / caisse
    const { resolvePrinter } = await loadPrinterRouting();
    const resolved = resolvePrinter(requestedPrinter, readPrinterRouting(), printers);
    const target = resolved.name ? printers.find(p => p.name === resolved.name) : null;
    console.log(`[PRINT] Poste "${requestedPrinter}" -> "${resolved.name}" (${resolved.reason})`);

    if (!target) {
      return { success: false, message: 'Aucune imprimante disponible pour l\'impression.' };
    }

    finalPrinterName = target.name;
    console.log(`[PRINT] Envoi vers : "${finalPrinterName}" (Cible originale : "${requestedPrinter}")`);

    let htmlContent;
    if (type === 'facture') {
      htmlContent = (await loadReceiptTemplate()).buildReceiptHTML(arg);
    } else if (type === 'bon_cuisine') {
      // Articles regroupés en « SUR PLACE » / « À EMPORTER » (modèle partagé avec le ticket client)
      const { splitItemsByServiceType, otherStationsText } = await loadReceiptTemplate();
      htmlContent = generateHTML({
        ...arg,
        serviceSections: splitItemsByServiceType(arg.items, arg.tableInfo?.serviceType),
        // Même ticket aussi envoyé à d'autres postes : « Pizza + Bar »
        otherStations: otherStationsText(arg.tableInfo?.stations, arg.printerName)
      }, type);
    } else {
      htmlContent = generateHTML(arg, type);
    }

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
