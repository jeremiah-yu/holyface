import AsyncStorage from '@react-native-async-storage/async-storage';
import * as LocalAuthentication from 'expo-local-authentication';
import * as SecureStore from 'expo-secure-store';
import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { AppState, Appearance } from 'react-native';
import { apiRequest } from '../api/client';
import { Lang } from '../i18n';
import { dark, light, Palette } from '../theme';

const SESSION_MS = 30 * 60 * 1000;

export type User = { id: number; username: string; full_name: string; email: string; role: string };

type LoginRes = {
  access_token: string;
  must_change_password: boolean;
  consent_complete: boolean;
  user: User;
};

type Ctx = {
  ready: boolean;
  token: string | null;
  user: User | null;
  mustChange: boolean;
  consentComplete: boolean;
  locked: boolean;
  lang: Lang;
  darkMode: boolean;
  colors: Palette;
  notifications: boolean;
  biometricEnabled: boolean;
  biometricReady: boolean;
  login: (id: string, password: string) => Promise<void>;
  loginWithBiometric: () => Promise<boolean>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
  setMustChange: (v: boolean) => void;
  setConsentComplete: (v: boolean) => void;
  setLang: (l: Lang) => void;
  setDarkMode: (v: boolean) => void;
  setNotifications: (v: boolean) => void;
  enableBiometric: (on: boolean) => Promise<void>;
  unlock: () => Promise<boolean>;
};

const AuthContext = createContext<Ctx | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [ready, setReady] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [mustChange, setMustChange] = useState(false);
  const [consentComplete, setConsentComplete] = useState(false);
  const [locked, setLocked] = useState(false);
  const [lang, setLangState] = useState<Lang>('en');
  const [darkMode, setDark] = useState(false);
  const [notifications, setNotifications] = useState(true);
  const [biometricEnabled, setBio] = useState(false);
  const [biometricReady, setBioReady] = useState(false);

  const colors = darkMode ? dark : light;

  const persistLastActive = async () => {
    await AsyncStorage.setItem('last_active', String(Date.now()));
  };

  const logout = useCallback(async () => {
    await SecureStore.deleteItemAsync('amgs_token').catch(() => undefined);
    setToken(null);
    setUser(null);
    setLocked(false);
  }, []);

  const checkTimeout = useCallback(async () => {
    const raw = await AsyncStorage.getItem('last_active');
    if (raw && Date.now() - Number(raw) > SESSION_MS) {
      await logout();
      return true;
    }
    return false;
  }, [logout]);

  useEffect(() => {
    (async () => {
      const [t, bio, langPref, darkPref, notif, bioReady] = await Promise.all([
        SecureStore.getItemAsync('amgs_token'),
        AsyncStorage.getItem('bio'),
        AsyncStorage.getItem('lang'),
        AsyncStorage.getItem('dark'),
        AsyncStorage.getItem('notif'),
        AsyncStorage.getItem('bio_ready'),
      ]);
      if (langPref === 'fil' || langPref === 'en') setLangState(langPref);
      if (darkPref === '1') setDark(true);
      if (darkPref == null && Appearance.getColorScheme() === 'dark') setDark(true);
      if (notif === '0') setNotifications(false);
      setBio(bio === '1');
      setBioReady(bioReady === '1');
      if (t) {
        const timedOut = await checkTimeout();
        if (!timedOut) {
          setToken(t);
          if (bio === '1') setLocked(true);
          try {
            const me = await apiRequest<{
              user: User;
              must_change_password: boolean;
              consent_complete: boolean;
            }>('/auth/me', { token: t });
            if (!me?.user?.role) {
              await SecureStore.deleteItemAsync('amgs_token');
            } else if (me.user.role !== 'student') {
              await SecureStore.deleteItemAsync('amgs_token');
            } else {
              setUser(me.user);
              setMustChange(me.must_change_password);
              setConsentComplete(me.consent_complete);
            }
          } catch {
            setLocked(false);
          }
        }
      }
      setReady(true);
    })();
  }, [checkTimeout]);

  useEffect(() => {
    const sub = AppState.addEventListener('change', (s) => {
      if (s === 'active') {
        checkTimeout().then((out) => {
          if (!out && biometricEnabled && token) setLocked(true);
        });
      }
    });
    return () => sub.remove();
  }, [biometricEnabled, token, checkTimeout]);

  const login = async (id: string, password: string) => {
    const data = await apiRequest<LoginRes>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ username: id, password }),
    });
    if (!data?.user?.role) {
      throw { message: 'Cannot reach Holy Face AMGS. Check your internet and try again.', status: 0 };
    }
    if (data.user.role !== 'student') {
      throw { message: 'This app is for students only. Teachers use the web workspace. The Registrar uses the desktop app.', status: 403 };
    }
    await SecureStore.setItemAsync('amgs_token', data.access_token);
    await persistLastActive();
    setToken(data.access_token);
    setUser(data.user);
    setMustChange(data.must_change_password);
    setConsentComplete(data.consent_complete);
    setLocked(false);
    if (biometricEnabled) {
      try {
        await SecureStore.setItemAsync('amgs_bio_login', JSON.stringify({ username: id, password }));
        await AsyncStorage.setItem('bio_ready', '1');
        setBioReady(true);
      } catch {
        // Account login already succeeded. Biometric login can be saved on the next password sign-in.
      }
    }
  };

  const refreshMe = async () => {
    if (!token) return;
    const me = await apiRequest<{
      user: User;
      must_change_password: boolean;
      consent_complete: boolean;
    }>('/auth/me', { token });
    setUser(me.user);
    setMustChange(me.must_change_password);
    setConsentComplete(me.consent_complete);
    await persistLastActive();
  };

  const setLang = (l: Lang) => {
    setLangState(l);
    AsyncStorage.setItem('lang', l);
  };
  const setDarkMode = (v: boolean) => {
    setDark(v);
    AsyncStorage.setItem('dark', v ? '1' : '0');
  };

  const enableBiometric = async (on: boolean) => {
    if (on) {
      const has = await LocalAuthentication.hasHardwareAsync();
      const enrolled = await LocalAuthentication.isEnrolledAsync();
      if (!has || !enrolled) throw new Error('Biometrics not available on this device.');
      const gate = await LocalAuthentication.authenticateAsync({
        promptMessage: 'Confirm biometric login',
        cancelLabel: 'Cancel',
      });
      if (!gate.success) throw new Error('Biometric confirmation was cancelled.');
    } else {
      await SecureStore.deleteItemAsync('amgs_bio_login').catch(() => undefined);
      await AsyncStorage.setItem('bio_ready', '0');
      setBioReady(false);
    }
    setBio(on);
    await AsyncStorage.setItem('bio', on ? '1' : '0');
  };

  const loginWithBiometric = async () => {
    const has = await LocalAuthentication.hasHardwareAsync();
    const enrolled = await LocalAuthentication.isEnrolledAsync();
    if (!has || !enrolled) throw new Error('Biometrics not available on this device.');
    const gate = await LocalAuthentication.authenticateAsync({
      promptMessage: 'Log in to Holy Face Student',
      cancelLabel: 'Cancel',
    });
    if (!gate.success) return false;
    const raw = await SecureStore.getItemAsync('amgs_bio_login');
    if (!raw) throw new Error('Log in with your password once while biometric unlock is on.');
    let saved: { username?: string; password?: string };
    try {
      saved = JSON.parse(raw) as { username?: string; password?: string };
    } catch {
      throw new Error('Log in with your password once while biometric unlock is on.');
    }
    if (!saved.username || !saved.password) {
      throw new Error('Log in with your password once while biometric unlock is on.');
    }
    try {
      await login(saved.username, saved.password);
    } catch (err) {
      const status = (err as { status?: number }).status;
      if (status === 401 || status === 423) {
        await SecureStore.deleteItemAsync('amgs_bio_login').catch(() => undefined);
        await AsyncStorage.setItem('bio_ready', '0');
        setBioReady(false);
      }
      throw err;
    }
    return true;
  };

  const unlock = async () => {
    const res = await LocalAuthentication.authenticateAsync({ promptMessage: 'Unlock Holy Face Student' });
    if (res.success) {
      setLocked(false);
      await persistLastActive();
      return true;
    }
    return false;
  };

  const value = useMemo(
    () => ({
      ready,
      token,
      user,
      mustChange,
      consentComplete,
      locked,
      lang,
      darkMode,
      colors,
      notifications,
      biometricEnabled,
      biometricReady,
      login,
      loginWithBiometric,
      logout,
      refreshMe,
      setMustChange,
      setConsentComplete,
      setLang,
      setDarkMode,
      setNotifications: (v: boolean) => {
        setNotifications(v);
        AsyncStorage.setItem('notif', v ? '1' : '0');
      },
      enableBiometric,
      unlock,
    }),
    [ready, token, user, mustChange, consentComplete, locked, lang, darkMode, colors, notifications, biometricEnabled, biometricReady, logout]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): Ctx {
  const c = useContext(AuthContext);
  if (!c) throw new Error('AuthProvider missing');
  return c;
}
