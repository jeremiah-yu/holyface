import { ReactNode } from 'react';

/** Host handshake now lives in the API client; this wrapper stays for layout compatibility. */
export function HostGate({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
