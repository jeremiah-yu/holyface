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

  async function load() {
    const params = new URLSearchParams();
    if (role) params.set('role', role);
    if (q) params.set('q', q);
    const r = await api<{ users: DirectoryUser[] }>(`/admin/users?${params.toString()}`, token);
    setRows(r.users);
  }

  useEffect(() => {
    api<Filters>('/teachers/filters', token).then(setFilters).catch(() => undefined);
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
      const r = await api<{ temporary_password: string }>('/admin/users', token, {
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
      setMsg('Account created. Give the temporary password privately — the user must change it at first login.');
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
    const r = await api<{ temporary_password: string | null }>('/admin/users', token, {
      method: 'PUT',
      body: JSON.stringify({ id: u.id, reset_password: true }),
    });
    setTempPw(r.temporary_password ?? '');
    setMsg(`Temporary password issued for ${u.full_name}.`);
  }

  return (
    <div className="card">
      <h2>Accounts</h2>
      <p className="hint">Create and maintain Registrar, teacher, and student logins. Passwords are hashed with Argon2id.</p>
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
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
