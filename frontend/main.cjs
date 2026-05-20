// main.cjs
const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const fs = require('fs');
const { PosPrinter } = require('electron-pos-printer');

let mainWindow;

function createWindow() {
  const preloadPath = path.join(__dirname, 'preload.cjs');
  
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    webPreferences: {
      preload: preloadPath,
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false
    },
  });

  if (!app.isPackaged) {
    mainWindow.loadURL('http://localhost:5173');
    mainWindow.webContents.openDevTools();
  } else {
    mainWindow.loadFile(path.join(__dirname, '../dist/index.html'));
  }

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

ipcMain.handle('print-receipt', async (event, arg) => {
    const options = {
        preview: false,
        margin: '0 0 0 0',
        copies: 1,
        printerName: arg.options?.printerName || 'POS-80', 
        timeOutPerLine: 400,
        pageSize: arg.options?.pageSize || '80mm'
    };
    try {
        await PosPrinter.print(arg.data, options);
        return { success: true };
    } catch (error) {
        return { success: false, message: error.message };
    }
});

ipcMain.handle('print-order', async (event, arg) => {
    const options = {
        preview: false,
        margin: '0 0 0 0',
        copies: 1,
        printerName: arg.printerName || 'POS-80', 
        timeOutPerLine: 400,
        pageSize: arg.options?.pageSize || '80mm'
    };
    try {
        await PosPrinter.print(arg.items, options);
        return { success: true };
    } catch (error) {
        return { success: false, message: error.message };
    }
});
