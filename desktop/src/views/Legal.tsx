import { useEffect, useState } from 'react';
import { api } from '../api';
import { POLICY_DISCLAIMER, PolicyBundle, PolicyDoc } from '../legal';

export default function Legal({ token }: { token: string }) {
  const [bundle, setBundle] = useState<PolicyBundle | null>(null);
  const [code, setCode] = useState('terms');

  useEffect(() => {
    api<PolicyBundle>('/legal/policies', token).then(setBundle);
  }, [token]);

  const doc: PolicyDoc | undefined = bundle?.documents.find((d) => d.code === code) ?? bundle?.documents[0];

  return (
    <div className="card help-card">
      <div className="toolbar">
        <h2>Terms and policies</h2>
        {(bundle?.documents ?? []).map((d) => (
          <button key={d.code} type="button" className={d.code === code ? '' : 'ghost'} onClick={() => setCode(d.code)}>
            {d.title}
          </button>
        ))}
      </div>
      {(bundle?.disclaimer ?? POLICY_DISCLAIMER).trim() ? (
        <p className="warn">{bundle?.disclaimer ?? POLICY_DISCLAIMER}</p>
      ) : null}
      {doc && (
        <>
          <p className="hint">
            Version {doc.version} · Last updated: {doc.last_updated}
          </p>
          {doc.sections.map((s) => (
            <section key={s.id}>
              <h3>
                {s.id} {s.title}
              </h3>
              <p style={{ whiteSpace: 'pre-wrap' }}>{s.body}</p>
            </section>
          ))}
        </>
      )}
    </div>
  );
}
