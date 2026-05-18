const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const fs = require('fs');
const { PosPrinter } = require('electron-pos-printer');

let mainWindow;

function createWindow() {
  const preloadPath = path.join(__dirname, 'preload.cjs');
  mainWindow = new BrowserWindow({
    width: 1280, height: 800,
    webPreferences: { preload: preloadPath, contextIsolation: true, nodeIntegration: false }
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

// Driver ESCPOS
ipcMain.handle('print-escpos', async (event, { type, config, data }) => {
  try {
    return await printerDriver.print(type, config, data);
  } catch (err) {
    console.error('[PRINT] Erreur ESCPOS:', err);
    return { success: false, message: err.message };
  }
});

// Impression standard via electron-pos-printer
async function executePrint(arg, type = 'receipt') {
  const data = arg.data || arg.items || [];
  const options = {
    preview: false,
    margin: '0 0 0 0',
    copies: 1,
    printerName: arg.printerName || arg.options?.printerName || 'POS-80',
    timeOutPerLine: 400,
    pageSize: arg.options?.pageSize || '80mm'
  };

  // Génération HTML pour test visuel
  try {
    const dir = path.join(__dirname, type === 'facture' ? 'factures' : 'bons_cuisine');
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const fileName = `${type}_${timestamp}.html`;
    const htmlContent = `
      <html>
      <body style="font-family: 'Courier New', Courier, monospace; width: 300px; padding: 20px; border: 1px solid #ccc; background: white;">
        ${data.map(item => {
          if (item.type === 'text') {
            const style = item.style || {};
            const textAlign = style.textAlign === 'center' ? 'center' : (style.textAlign === 'right' ? 'right' : 'left');
            const fontWeight = style.fontWeight === 'bold' ? 'bold' : 'normal';
            return `<div style="text-align:${textAlign}; font-weight:${fontWeight}; margin-bottom: 5px; font-size:${style.fontSize || '14px'};">${item.value.replace(/\n/g, '<br>')}</div>`;
          }
          if (item.type === 'table-header') {
            return `<table style="width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size:${item.style.fontSize || '12px'}; font-weight: bold;">
                      <tr>
                        <td style="width: 15%;">Qte</td>
                        <td style="width: 60%;">Désignation</td>
                        <td style="width: 25%; text-align: right;">Total</td>
                      </tr>
                    </table>`;
          }
          if (item.type === 'table-row') {
            return `<table style="width: 100%; border-collapse: collapse; font-size:${item.style.fontSize || '12px'};">
                      <tr>
                        <td style="width: 15%; padding-left: 5px;">${item.value.qte}</td>
                        <td style="width: 60%;">${item.value.name}</td>
                        <td style="width: 25%; text-align: right;">${item.value.total}</td>
                      </tr>
                    </table>`;
          }
          return '';
        }).join('')}
      </body>
      </html>
    `;
    const htmlPath = path.join(dir, fileName);
    fs.writeFileSync(htmlPath, htmlContent);
    console.log('✅ Rendu HTML généré dans:', htmlPath);
  } catch (e) { 
    console.error('❌ Échec génération HTML:', e); 
  }

  try {
    await PosPrinter.print(data, options);
    return { success: true };
  } catch (error) {
    console.error('[PRINT] Erreur:', error);
    return { success: false, message: error.message };
  }
}

ipcMain.handle('print-receipt', async (event, arg) => { return await executePrint(arg, 'facture'); });
ipcMain.handle('print-order', async (event, arg) => { return await executePrint(arg, 'bon_cuisine'); });
ipcMain.handle('get-printers', async () => {
  return await mainWindow.webContents.getPrintersAsync();
});
