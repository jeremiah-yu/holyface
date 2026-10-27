const { app, BrowserWindow, ipcMain, safeStorage, dialog } = require('electron');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');

const isDev = !app.isPackaged;

function cachePath() {
  return path.join(app.getPath('userData'), 'amgs-cache.bin');
}

function fallbackKey() {
  return crypto.scryptSync(`${app.getPath('userData')}|holyface-amgs`, 'hfj-lyceum-sjose', 32);
}

function encryptPayload(text) {
  if (safeStorage.isEncryptionAvailable()) {
    const buf = safeStorage.encryptString(text);
    return Buffer.concat([Buffer.from('S1'), buf]);
  }
  const iv = crypto.randomBytes(12);
  const cipher = crypto.createCipheriv('aes-256-gcm', fallbackKey(), iv);
  const enc = Buffer.concat([cipher.update(text, 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();
  return Buffer.concat([Buffer.from('A1'), iv, tag, enc]);
}

function decryptPayload(buf) {
  const marker = buf.subarray(0, 2).toString('utf8');
  if (marker === 'S1') {
    return safeStorage.decryptString(buf.subarray(2));
  }
  if (marker === 'A1') {
    const iv = buf.subarray(2, 14);
    const tag = buf.subarray(14, 30);
    const enc = buf.subarray(30);
    const decipher = crypto.createDecipheriv('aes-256-gcm', fallbackKey(), iv);
    decipher.setAuthTag(tag);
    return Buffer.concat([decipher.update(enc), decipher.final()]).toString('utf8');
  }
  throw new Error('Unrecognized cache format');
}

function createWindow() {
  const win = new BrowserWindow({
    width: 1440,
    height: 900,
    minWidth: 1100,
    minHeight: 700,
    title: 'Holy Face AMGS — Teacher',
    backgroundColor: '#0b1f3a',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });

  if (isDev) {
    win.loadURL('http://localhost:5173');
  } else {
    win.loadFile(path.join(__dirname, '../dist/index.html'));
  }
}

ipcMain.handle('secure-load', () => {
  try {
    const file = cachePath();
    if (!fs.existsSync(file)) return { ok: true, data: null, encrypted: true };
    const raw = fs.readFileSync(file);
    return { ok: true, data: JSON.parse(decryptPayload(raw)), encrypted: true };
  } catch (err) {
    return { ok: false, error: String(err.message || err) };
  }
});

ipcMain.handle('secure-save', (_event, payload) => {
  try {
    const text = JSON.stringify(payload);
    fs.writeFileSync(cachePath(), encryptPayload(text));
    return { ok: true };
  } catch (err) {
    return { ok: false, error: String(err.message || err) };
  }
});

ipcMain.handle('encryption-available', () => ({
  ok: true,
  safeStorage: safeStorage.isEncryptionAvailable(),
}));

ipcMain.handle('export-file', async (_event, opts) => {
  const { canceled, filePath } = await dialog.showSaveDialog({
    defaultPath: opts?.defaultName || 'holyface-export.csv',
    filters: opts?.filters || [{ name: 'CSV', extensions: ['csv'] }],
  });
  if (canceled || !filePath) return { canceled: true };
  fs.writeFileSync(filePath, opts?.content ?? '', 'utf8');
  return { canceled: false, filePath };
});

ipcMain.handle('print-page', async (event) => {
  const win = BrowserWindow.fromWebContents(event.sender);
  if (!win) return { ok: false };
  win.webContents.print({ silent: false });
  return { ok: true };
});

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});
