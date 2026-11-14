import { FormEvent, useEffect, useState } from 'react';
import { api } from '../api';

type DirectoryUser = {
  id: number;
  username: string;
  email: string;
  full_name: string;
  is_active: number;
  last_login_at: string | null;
  must_change_password: number;
  role: string;
  employee_no?: string | null;
  department?: string | null;
  student_number?: string | null;
  grade_level?: number | null;
  section_code?: string | null;
  strand?: string | null;
};

type Filters = {
  school_years: { id: number; label: string }[];
  strands: { id: number; code: string; name: string }[];
};

export default function Users({ token }: { token: string }) {
  const [rows, setRows] = useState<DirectoryUser[]>([]);
  const [role, setRole] = useState('');
  const [q, setQ] = useState('');
  const [filters, setFilters] = useState<Filters | null>(null);
  const [msg, setMsg] = useState('');
  const [error, setError] = useState('');
  const [tempPw, setTempPw] = useState('');
  const [mail, setMail] = useState({ from: 'registrar@holyface.school', host: 'smtp.gmail.com', port: 587, user: '', password_set: false, ready: false });
  const [importRows, setImportRows] = useState<
    { row: number; username?: string; status: string; message: string; email_sent?: boolean; temporary_password?: string }[]
  >([]);

  async function load() {
    const params = new URLSearchParams();
    if (role) params.set('role', role);
    if (q) params.set('q', q);
    const r = await api<{ users: DirectoryUser[] }>(`/admin/users?${params.toString()}`, token);
    setRows(r.users);
  }

  useEffect(() => {
    api<Filters>('/teachers/filters', token).then(setFilters).catch(() => undefined);
    api<{ mail: typeof mail }>('/admin/mail', token).then((r) => setMail(r.mail)).catch(() => undefined);
  }, [token]);

  useEffect(() => {
    load().catch((e: Error) => setError(e.message));
  }, [token, role, q]);

  async function create(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    setError('');
    setMsg('');
    setTempPw('');
    try {
      const r = await api<{ temporary_password: string; email_sent?: boolean; mail_ready?: boolean; email?: string }>('/admin/users', token, {
        method: 'POST',
        body: JSON.stringify({
          role: fd.get('role'),
          full_name: fd.get('full_name'),
          email: fd.get('email'),
          username: fd.get('username'),
          password: fd.get('password') || undefined,
          employee_no: fd.get('employee_no') || undefined,
          department: fd.get('department') || undefined,
          student_number: fd.get('student_number') || undefined,
          strand_id: fd.get('strand_id') ? Number(fd.get('strand_id')) : undefined,
          grade_level: fd.get('grade_level') ? Number(fd.get('grade_level')) : undefined,
          section_code: fd.get('section_code') || undefined,
          school_year_id: fd.get('school_year_id') ? Number(fd.get('school_year_id')) : undefined,
        }),
      });
      setTempPw(r.temporary_password);
      const sentTo = String(fd.get('email') || r.email || '');
      setMsg(
        r.email_sent
          ? `Account created. The username and temporary password were sent to ${sentTo}.`
          : r.mail_ready
            ? `Account created, but the email to ${sentTo} could not be sent. Give the temporary password below.`
            : 'Account created, but nothing was emailed yet. Save the sending email first, then use Reset password to send it.'
      );
      (e.target as HTMLFormElement).reset();
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  }

  async function toggle(u: DirectoryUser) {
    await api('/admin/users', token, {
      method: 'PUT',
      body: JSON.stringify({ id: u.id, is_active: u.is_active ? 0 : 1 }),
    });
    await load();
  }

  async function resetPassword(u: DirectoryUser) {
    if (!window.confirm(`Reset password for ${u.full_name}?`)) return;
    const r = await api<{ temporary_password: string | null; email_sent?: boolean; email?: string; mail_ready?: boolean }>('/admin/users', token, {
      method: 'PUT',
      body: JSON.stringify({ id: u.id, reset_password: true }),
    });
    setTempPw(r.temporary_password ?? '');
    setMsg(
      r.email_sent
        ? `A new temporary password was emailed to ${r.email || u.email}.`
        : `Password reset for ${u.full_name}, but the email was not sent. Give the temporary password below.`
    );
  }

  async function remove(u: DirectoryUser) {
    if (!window.confirm(`Delete the account of ${u.full_name} (${u.username})? This cannot be undone. A professor's classes are moved to another professor.`)) return;
    setError('');
    setMsg('');
    try {
      await api('/admin/users/delete', token, {
        method: 'POST',
        body: JSON.stringify({ id: u.id }),
      });
      setMsg(`${u.full_name} was deleted.`);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  }

  function downloadTemplate() {
    const csv = [
      'full_name,email,username,student_number,strand,grade_level,section_code,guardian_name,guardian_contact',
      'Juan Miguel Dela Rosa,juan@example.com,s010,123456789021,STEM,11,STEM-A,Rosa Dela Rosa,09171234501',
    ].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'holyface-students.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  async function importCsv(file: File) {
    setError('');
    setMsg('');
    setImportRows([]);
    try {
      const csv = await file.text();
      const r = await api<{
        created: number;
        emailed: number;
        results: { row: number; username?: string; status: string; message: string; email_sent?: boolean; temporary_password?: string }[];
      }>('/admin/users/import', token, {
        method: 'POST',
        body: JSON.stringify({ csv }),
      });
      setImportRows(r.results);
      setMsg(`Created ${r.created} student account${r.created === 1 ? '' : 's'}. Emailed ${r.emailed}.`);
      await load();
    } catch (err) {
      setError((err as Error).message);
    }
  }

  return (
    <div className="card">
      <h2>Accounts</h2>
      <p className="hint">
        Creating an account emails the username and temporary password to that person's email.
        The message is sent from the sending mailbox below. Students can also be imported from a CSV.
      </p>
      <form
        className="filters user-form"
        onSubmit={async (e) => {
          e.preventDefault();
          const fd = new FormData(e.currentTarget);
          setError('');
          setMsg('');
          try {
            const saved = await api<{ mail: typeof mail }>('/admin/mail', token, {
              method: 'PUT',
              body: JSON.stringify({
                from: fd.get('from'),
                host: fd.get('host'),
                port: Number(fd.get('port') || 587),
                user: fd.get('user'),
                pass: fd.get('pass') || '',
              }),
            });
            setMail(saved.mail);
            setMsg(`Sending email saved. New accounts will be emailed from ${saved.mail.from}.`);
          } catch (err) {
            setError((err as Error).message);
          }
        }}
      >
        <label>
          Sending email
          <input name="from" type="email" required defaultValue={mail.from} key={`from-${mail.from}`} />
        </label>
        <label>
          SMTP host
          <input name="host" required defaultValue={mail.host} key={`host-${mail.host}`} />
        </label>
        <label>
          Port
          <input name="port" type="number" required defaultValue={mail.port} key={`port-${mail.port}`} />
        </label>
        <label>
          Mailbox username
          <input name="user" type="email" required defaultValue={mail.user} key={`user-${mail.user}`} autoComplete="off" />
        </label>
        <label>
          App password
          <input name="pass" type="password" autoComplete="new-password" placeholder={mail.password_set ? 'Saved — leave blank to keep' : 'Gmail app password'} />
        </label>
        <button type="submit">Save sending email</button>
        <button
          type="button"
          className="ghost"
          onClick={async () => {
            setError('');
            setMsg('');
            try {
              const r = await api<{ sent_to: string }>('/admin/mail/test', token, { method: 'POST', body: '{}' });
              setMsg(`Test email sent to ${r.sent_to}.`);
            } catch (err) {
              setError((err as Error).message);
            }
          }}
        >
          Send test email
        </button>
      </form>
      <p className="hint">
        InfinityFree cannot send mail by itself. Use a Gmail address with an app password, host smtp.gmail.com, and port 587.
        That mailbox is the sender. {mail.ready ? 'Sending email is ready.' : 'Sending email is not set yet, so new accounts are not emailed.'}
      </p>
      {msg && <p className="ok">{msg}</p>}
      {tempPw && (
        <p className="warn">
          Temporary password: <code>{tempPw}</code>
        </p>
      )}
      {error && <p className="error">{error}</p>}

      <form onSubmit={create} className="filters user-form">
        <label>
          Role
          <select name="role" defaultValue="teacher">
            <option value="teacher">Professor</option>
            <option value="student">Student</option>
            <option value="admin">Registrar</option>
          </select>
        </label>
        <label>
          Full name
          <input name="full_name" required />
        </label>
        <label>
          Username
          <input name="username" required autoComplete="off" />
        </label>
        <label>
          Email
          <input name="email" type="email" required />
        </label>
        <label>
          Password (optional)
          <input name="password" type="password" autoComplete="new-password" placeholder="Auto-generate if blank" />
        </label>
        <label>
          Employee no. (professor)
          <input name="employee_no" />
        </label>
        <label>
          Department (professor)
          <input name="department" />
        </label>
        <label>
          Student number
          <input name="student_number" />
        </label>
        <label>
          Strand
          <select name="strand_id">
            <option value="">—</option>
            {(filters?.strands ?? []).map((s) => (
              <option key={s.id} value={s.id}>
                {s.code}
              </option>
            ))}
          </select>
        </label>
        <label>
          Grade
          <select name="grade_level" defaultValue="11">
            <option value="11">11</option>
            <option value="12">12</option>
          </select>
        </label>
        <label>
          Section code
          <input name="section_code" placeholder="STEM-A" />
        </label>
        <label>
          School year
          <select name="school_year_id">
            {(filters?.school_years ?? []).map((y) => (
              <option key={y.id} value={y.id}>
                {y.label}
              </option>
            ))}
          </select>
        </label>
        <button type="submit">Create account</button>
      </form>

      <div className="filters">
        <label>
          Import students (CSV)
          <input
            type="file"
            accept=".csv,text/csv"
            onChange={(e) => {
              const file = e.target.files?.[0];
              e.target.value = '';
              if (file) void importCsv(file);
            }}
          />
        </label>
        <button type="button" className="ghost" onClick={downloadTemplate}>
          Download CSV template
        </button>
      </div>
      <p className="hint">
        Required columns: full_name, email, username, student_number, strand, grade_level, section_code.
        Strand is ABM, HUMSS, ICT, HE, or STEM. A row with a blank field is skipped and is not emailed.
      </p>
      {importRows.length > 0 && (
        <table>
          <thead>
            <tr>
              <th>Row</th>
              <th>Username</th>
              <th>Result</th>
            </tr>
          </thead>
          <tbody>
            {importRows.map((row) => (
              <tr key={`${row.row}-${row.username ?? ''}`}>
                <td>{row.row}</td>
                <td>{row.username || '—'}</td>
                <td>
                  {row.message}
                  {row.temporary_password ? (
                    <div className="warn">
                      Temporary password: <code>{row.temporary_password}</code>
                    </div>
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      <div className="filters">
        <label>
          Filter role
          <select value={role} onChange={(e) => setRole(e.target.value)}>
            <option value="">All</option>
            <option value="admin">Registrar</option>
            <option value="teacher">Professor</option>
            <option value="student">Student</option>
          </select>
        </label>
        <label>
          Search
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Name, username, student number" />
        </label>
      </div>

      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Role</th>
            <th>Username</th>
            <th>Details</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {rows.map((u) => (
            <tr key={u.id}>
              <td>{u.full_name}</td>
              <td>{u.role === 'admin' ? 'Registrar' : u.role === 'teacher' ? 'Professor' : u.role}</td>
              <td>
                {u.username}
                <div className="hint">{u.email}</div>
              </td>
              <td className="hint">
                {u.role === 'teacher' && (u.employee_no || u.department)}
                {u.role === 'student' &&
                  [u.student_number, u.strand, u.grade_level && `G${u.grade_level}`, u.section_code]
                    .filter(Boolean)
                    .join(' · ')}
              </td>
              <td>
                {u.is_active ? 'Active' : 'Inactive'}
                {u.must_change_password ? ' · must change password' : ''}
                <div className="hint">{u.last_login_at ? `Last login ${u.last_login_at}` : 'Never logged in'}</div>
              </td>
              <td>
                <button type="button" className="ghost" onClick={() => void toggle(u)}>
                  {u.is_active ? 'Deactivate' : 'Activate'}
                </button>{' '}
                <button type="button" className="ghost" onClick={() => void resetPassword(u)}>
                  Reset password
                </button>{' '}
                <button type="button" className="ghost" onClick={() => void remove(u)}>
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
