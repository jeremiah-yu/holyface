export function isElectron(): boolean {
  return typeof window !== 'undefined' && typeof window.holyface?.secureLoad === 'function';
}
