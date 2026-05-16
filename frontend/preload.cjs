const { contextBridge, ipcRenderer } = require('electron');

console.log('Preload script: Initializing...');

try {
  contextBridge.exposeInMainWorld('electronAPI', {
    printReceipt: (data) => ipcRenderer.invoke('print-receipt', data),
    printOrder: (data) => ipcRenderer.invoke('print-order', data)
  });
  console.log('Preload script: API exposed successfully to window.electronAPI');
} catch (error) {
  console.error('Preload script: Failed to expose API:', error);
}
