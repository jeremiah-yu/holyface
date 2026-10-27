import { FormEvent, useEffect, useRef, useState } from 'react';
import { api } from '../api';
import { POLICY_DISCLAIMER, PolicyBundle } from '../legal';

export default function AcceptPolicies({
  token,
  requiresGuardian,
  onAccepted,
}: {
  token: string;
  requiresGuardian: boolean;
  onAccepted: () => void;
}) {
  const [bundle, setBundle] = useState<PolicyBundle | null>(null);
  const [scrolled, setScrolled] = useState(false);
  const [accepted, setAccepted] = useState(false);
  const [guardian, setGuardian] = useState('');
  const [rel, setRel] = useState('parent/guardian');
  const [confirmed, setConfirmed] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const box = useRef<HTMLDivElement>(null);

  useEffect(() => {
    api<PolicyBundle>('/legal/policies', token).then((r) => {
      setBundle(r);
      requestAnimationFrame(() => {
        const el = box.current;
        if (el && el.scrollHeight <= el.clientHeight + 8) setScrolled(true);
      });
    });
  }, [token]);

  function onScroll() {
    const el = box.current;
    if (!el) return;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 24) setScrolled(true);
  }

  async function submit(e: FormEvent) {
    e.preventDefault();
    if (!scrolled || !accepted) {
      setError('Please scroll to the end of the policies and confirm you agree.');
      return;
    }
    if (requiresGuardian && (guardian.trim().length < 2 || !confirmed)) {
      setError('Parent or guardian name and confirmation are required for users under 18.');
      return;
    }
    setBusy(true);
    setError('');
    try {
      await api('/legal/accept', token, {
        method: 'POST',
        body: JSON.stringify({
          scrolled: true,
          accepted: true,
          guardian_name: guardian.trim(),
          relationship: rel,
          guardian_confirmed: confirmed,
        }),
      });
      onAccepted();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="login-wrap">
      <div className="card login" style={{ maxWidth: 720, textAlign: 'left' }}>
        <h2>Accept Terms and Policies</h2>
        <p>
          Version {bundle?.version_bundle ?? '—'} · Last updated:{' '}
          {bundle?.documents[0]?.last_updated ?? '—'}
        </p>
        {(bundle?.disclaimer ?? POLICY_DISCLAIMER).trim() ? (
          <p className="warn">{bundle?.disclaimer ?? POLICY_DISCLAIMER}</p>
        ) : null}
        <div ref={box} className="policy-scroll" onScroll={onScroll} tabIndex={0}>
          {(bundle?.documents ?? []).map((doc) => (
            <section key={doc.code}>
              <h3>
                {doc.title} <small>v{doc.version}</small>
              </h3>
              {doc.sections.map((s) => (
                <div key={s.id}>
                  <h4>
                    {s.id} {s.title}
                  </h4>
                  <p style={{ whiteSpace: 'pre-wrap' }}>{s.body}</p>
                </div>
              ))}
            </section>
          ))}
          <p>
            <strong>End of policies.</strong>
          </p>
        </div>
        <form onSubmit={submit}>
          <label>
            <input type="checkbox" checked={accepted} disabled={!scrolled} onChange={(e) => setAccepted(e.target.checked)} /> I
            have read and agree to the Terms of Use, Privacy Policy, and AI Use Policy.
            {!scrolled ? ' (Scroll to the end to enable.)' : ''}
          </label>
          {requiresGuardian && (
            <>
              <label>
                Parent / guardian full name
                <input value={guardian} onChange={(e) => setGuardian(e.target.value)} required />
              </label>
              <label>
                Relationship
                <input value={rel} onChange={(e) => setRel(e.target.value)} />
              </label>
              <label>
                <input type="checkbox" checked={confirmed} onChange={(e) => setConfirmed(e.target.checked)} /> I confirm I am
                the parent/guardian (or I am with them now).
              </label>
            </>
          )}
          {error && <p className="error">{error}</p>}
          <button type="submit" disabled={busy || !scrolled || !accepted}>
            {busy ? 'Saving…' : 'Accept and continue'}
          </button>
        </form>
      </div>
    </div>
  );
}
