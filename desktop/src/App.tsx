import { useCallback, useEffect, useRef, useState } from 'react';
import { api, getApiBase, probeApi, setApiBase } from './api';
import { isElectron } from './platform';
import { loadCache, saveCache } from './sync';
import type { SecureCache, User } from './types';
import RegistrarWorkspace from './RegistrarWorkspace';
import TeacherWorkspace from './TeacherWorkspace';
import AcceptPolicies from './views/AcceptPolicies';
import Login from './views/Login';
import WrongRole from './views/WrongRole';

export default function App() {
  const electron = isElectron();
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [error, setError] = useState('');
  const [consentComplete, setConsentComplete] = useState(false);
  const [requiresGuardian, setRequiresGuardian] = useState(false);
  const [blocked, setBlocked] = useState<'teacher-on-desktop' | 'admin-on-web' | 'student' | null>(null);
  const cacheRef = useRef<SecureCache>({});

  const persist = useCallback(async (patch: Partial<SecureCache>) => {
    cacheRef.current = { ...cacheRef.current, ...patch };
    await saveCache(cacheRef.current);
  }, []);

  const logout = useCallback(async () => {
    setToken(null);
    setUser(null);
    setBlocked(null);
    setConsentComplete(false);
    await persist({ token: null, user: null });
  }, [persist]);

  const applySession = useCallback(
    (nextUser: User, access: string, consent: boolean, guardian?: boolean) => {
      if (!nextUser?.role) {
        setError('Cannot reach the Holy Face AMGS server. Check your internet connection.');
        return false;
      }
      if (nextUser.role === 'student') {
        setBlocked('student');
        return false;
      }
      if (electron && nextUser.role !== 'admin') {
        setBlocked('teacher-on-desktop');
        return false;
      }
      if (!electron && nextUser.role !== 'teacher') {
        setBlocked('admin-on-web');
        return false;
      }
      setToken(access);
      setUser(nextUser);
      setConsentComplete(consent);
      setRequiresGuardian(!!guardian);
      return true;
    },
    [electron]
  );

  const boot = useCallback(async () => {
    const cache = await loadCache();
    cacheRef.current = cache;
    if (cache.apiBase) setApiBase(cache.apiBase);
    try {
      const base = await probeApi();
      await persist({ apiBase: base });
    } catch {
      /* offline */
    }
    if (cache.token && cache.user) {
      applySession(cache.user, cache.token, true);
    }
  }, [persist, applySession]);

  useEffect(() => {
    void boot();
  }, [boot]);

  useEffect(() => {
    if (!token) return;
    api<{ user: User; consent_complete: boolean; requires_guardian?: boolean }>('/auth/me', token)
      .then((r) => {
        if (!applySession(r.user, token, !!r.consent_complete, r.requires_guardian)) {
          void logout();
        }
      })
      .catch(() => {
        void logout();
      });
  }, [token, applySession, logout]);

  async function onLogin(username: string, password: string) {
    setError('');
    setBlocked(null);
    try {
      await probeApi();
      const data = await api<{
        access_token: string;
        user: User;
        consent_complete: boolean;
        requires_guardian?: boolean;
      }>('/auth/login', null, {
        method: 'POST',
        body: JSON.stringify({ username, password }),
      });
      const ok = applySession(data.user, data.access_token, !!data.consent_complete, data.requires_guardian);
      if (!ok) return;
      await persist({ token: data.access_token, user: data.user, apiBase: getApiBase() });
    } catch (err) {
      setError((err as Error).message);
    }
  }

  if (blocked === 'teacher-on-desktop') {
    return (
      <WrongRole
        title="Professors encode on the web"
        body="Buksan ang professor workspace sa website (Professor login). This desktop app is for the Registrar — files and system maintenance only."
        onBack={() => void logout()}
      />
    );
  }
  if (blocked === 'admin-on-web') {
    return (
      <WrongRole
        title="Use the Registrar desktop app"
        body="Grade encoding is on the web for professors. Keep files, accounts, and locks in the Windows Registrar app."
        onBack={() => void logout()}
      />
    );
  }
  if (blocked === 'student') {
    return (
      <WrongRole
        title="Use the student mobile app"
        body="Learner accounts open grades and the official card on the Holy Face Student app."
        onBack={() => void logout()}
      />
    );
  }

  if (!token || !user) {
    return (
      <Login
        onLogin={onLogin}
        error={error}
        subtitle={electron ? 'Registrar desktop' : 'Professor workspace'}
        defaultUsername={electron ? 'registrar' : 'mreyes'}
      />
    );
  }

  if (!consentComplete) {
    return (
      <AcceptPolicies
        token={token}
        requiresGuardian={requiresGuardian}
        onAccepted={() => setConsentComplete(true)}
      />
    );
  }

  if (electron) {
    return <RegistrarWorkspace token={token} user={user} logout={logout} />;
  }

  return (
    <TeacherWorkspace token={token} user={user} cacheRef={cacheRef} persist={persist} logout={logout} />
  );
}
