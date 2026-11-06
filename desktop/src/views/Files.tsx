import { FormEvent, useEffect, useState } from 'react';
import { api } from '../api';

type VaultFile = {
  id: number;
  title: string;
  original_name: string;
  mime: string;
  byte_size: number;
  category: string;
  notes: string | null;
  created_at: string;
  uploaded_by_name: string;
};

const CATEGORY_LABEL: Record<string, string> = {
  circular: 'Circular',
  memo: 'Memo',
  form: 'Form',
  policy: 'Policy',
  backup: 'Backup',
  student_record: 'Student record',
  other: 'Other',
};

function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

export default function Files({ token }: { token: string }) {
  const [files, setFiles] = useState<VaultFile[]>([]);
  const [categories, setCategories] = useState<string[]>(Object.keys(CATEGORY_LABEL));
  const [category, setCategory] = useState('');
  const [q, setQ] = useState('');
  const [msg, setMsg] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function load() {
    const params = new URLSearchParams();
    if (category) params.set('category', category);
    if (q) params.set('q', q);
    const r = await api<{ files: VaultFile[]; categories: string[] }>(`/admin/files?${params.toString()}`, token);
    setFiles(r.files);
    if (r.categories?.length) setCategories(r.categories);
  }

  useEffect(() => {
    load().catch((e: Error) => setError(e.message));
  }, [token, category, q]);

  async function onUpload(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const form = e.currentTarget;
    const fd = new FormData(form);
    const file = (fd.get('file') as File | null) ?? null;
    if (!file) return;
    if (file.size > 15_728_640) {
      setError('Each file must be 15 MB or smaller.');
      return;
    }
    setBusy(true);
    setError('');
    setMsg('');
    try {
      const content_base64 = await new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
          const s = String(reader.result || '');
          const comma = s.indexOf(',');
          resolve(comma >= 0 ? s.slice(comma + 1) : s);
        };
        reader.onerror = () => reject(reader.error ?? new Error('Could not read the file.'));
        reader.readAsDataURL(file);
      });
      await api('/admin/files', token, {
        method: 'POST',
        body: JSON.stringify({
          filename: file.name,
          content_base64,
          title: String(fd.get('title') || ''),
          category: String(fd.get('category') || 'other'),
          notes: String(fd.get('notes') || ''),
        }),
      });
      form.reset();
      setMsg(`Saved ${file.name} to the registrar vault.`);
      await load();
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function download(id: number, filename: string) {
    setError('');
    try {
      const r = await api<{ filename: string; content_base64: string }>(`/admin/files/download?id=${id}`, token);
      if (window.holyface?.exportFile) {
        const ext = filename.includes('.') ? filename.split('.').pop() : 'bin';
        await window.holyface.exportFile({
          defaultName: r.filename || filename,
          content: r.content_base64,
          encoding: 'base64',
          filters: [{ name: 'File', extensions: [ext || 'bin'] }],
        });
        return;
      }
      const bin = atob(r.content_base64);
      const bytes = new Uint8Array(bin.length);
      for (let i = 0; i < bin.length; i += 1) bytes[i] = bin.charCodeAt(i);
      const url = URL.createObjectURL(new Blob([bytes]));
      const a = document.createElement('a');
      a.href = url;
      a.download = r.filename || filename;
      a.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      setError((err as Error).message);
    }
  }

  async function remove(id: number, name: string) {
    if (!window.confirm(`Delete “${name}” from the vault? This cannot be undone.`)) return;
    await api('/admin/files', token, { method: 'DELETE', body: JSON.stringify({ id }) });
    await load();
  }

  return (
    <div className="card">
      <h2>School file vault</h2>
      <p className="hint">
        Keep circulars, forms, backups, and official records on the school server. Maximum 15 MB per file. PDF, Word,
        Excel, images, and ZIP only.
      </p>
      {msg && <p className="ok">{msg}</p>}
      {error && <p className="error">{error}</p>}

      <form onSubmit={onUpload} className="filters">
        <label>
          Title
          <input name="title" placeholder="e.g. Enrollment circular SY 2025-2026" />
        </label>
        <label>
          Category
          <select name="category" defaultValue="circular">
            {categories.map((c) => (
              <option key={c} value={c}>
                {CATEGORY_LABEL[c] ?? c}
              </option>
            ))}
          </select>
        </label>
        <label>
          Notes
          <input name="notes" placeholder="Optional" />
        </label>
        <label>
          File
          <input name="file" type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.zip" />
        </label>
        <button type="submit" disabled={busy}>
          {busy ? 'Saving…' : 'Keep file'}
        </button>
      </form>

      <div className="filters">
        <label>
          Filter category
          <select value={category} onChange={(e) => setCategory(e.target.value)}>
            <option value="">All</option>
            {categories.map((c) => (
              <option key={c} value={c}>
                {CATEGORY_LABEL[c] ?? c}
              </option>
            ))}
          </select>
        </label>
        <label>
          Search
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Title or filename" />
        </label>
      </div>

      <table>
        <thead>
          <tr>
            <th>Title</th>
            <th>File</th>
            <th>Category</th>
            <th>Size</th>
            <th>Uploaded</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {files.length === 0 && (
            <tr>
              <td colSpan={6} className="muted">
                No files yet.
              </td>
            </tr>
          )}
          {files.map((f) => (
            <tr key={f.id}>
              <td>
                <strong>{f.title}</strong>
                {f.notes ? <div className="hint">{f.notes}</div> : null}
              </td>
              <td>{f.original_name}</td>
              <td>{CATEGORY_LABEL[f.category] ?? f.category}</td>
              <td>{formatBytes(f.byte_size)}</td>
              <td>
                {f.created_at}
                <div className="hint">{f.uploaded_by_name}</div>
              </td>
              <td>
                <button type="button" className="ghost" onClick={() => void download(f.id, f.original_name)}>
                  Download
                </button>{' '}
                <button type="button" className="ghost" onClick={() => void remove(f.id, f.title)}>
                  Delete
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
