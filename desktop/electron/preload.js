const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('holyface', {
  apiBaseUrl: process.env.AMGS_API_URL || 'http://localhost:8089',
  secureLoad: () => ipcRenderer.invoke('secure-load'),
  secureSave: (data) => ipcRenderer.invoke('secure-save', data),
  encryptionAvailable: () => ipcRenderer.invoke('encryption-available'),
  exportFile: (opts) => ipcRenderer.invoke('export-file', opts),
  printPage: () => ipcRenderer.invoke('print-page'),
});
