// preload.cjs
const { contextBridge, ipcRenderer } = require('electron');

console.log('[Preload] Script loaded successfully');

// Exposer l'API Electron au renderer
contextBridge.exposeInMainWorld('electronAPI', {
  // Impression PDF
  printPDFReceipt: (data) => {
    console.log('[Preload] printPDFReceipt called with printer:', data.printerName);
    return ipcRenderer.invoke('print-pdf-receipt', data);
  },

  printPDFOrder: (data) => {
    console.log('[Preload] printPDFOrder called');
    return ipcRenderer.invoke('print-pdf-order', data);
  },

  generateAndPrintPDFReceipt: (data) => {
    console.log('[Preload] generateAndPrintPDFReceipt called');
    return ipcRenderer.invoke('generate-and-print-pdf-receipt', data);
  },

  generatePDFReceipt: (data) => {
    console.log('[Preload] generatePDFReceipt called');
    return ipcRenderer.invoke('generate-pdf-receipt', data);
  },

  generatePDFOrder: (data) => {
    console.log('[Preload] generatePDFOrder called');
    return ipcRenderer.invoke('generate-pdf-order', data);
  },

  printReceipt: (data) => {
    console.log('[Preload] printReceipt called');
    return ipcRenderer.invoke('print-receipt', data);
  },

  printOrder: (data) => {
    console.log('[Preload] printOrder called');
    return ipcRenderer.invoke('print-order', data);
  },

  getPrinters: () => {
    console.log('[Preload] getPrinters called');
    return ipcRenderer.invoke('get-printers');
  },

  openFile: (filePath) => {
    console.log('[Preload] openFile called:', filePath);
    return ipcRenderer.invoke('open-file', filePath);
  },

  printSessionSummary: (data) => {
    console.log('[Preload] printSessionSummary called');
    return ipcRenderer.invoke('print-session-summary', data);
  },

  sendRawCommands: (data) => {
    console.log('[Preload] sendRawCommands called');
    return ipcRenderer.invoke('send-raw-commands', data);
  }
});

console.log('[Preload] electronAPI exposed to window');
