import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, Text, View } from 'react-native';
import { apiRequest } from '../src/api/client';
import { useAuth } from '../src/auth/AuthContext';
import { PasswordField } from '../src/components/PasswordField';

export default function ChangePasswordScreen() {
  const { token, setMustChange, colors } = useAuth();
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [busy, setBusy] = useState(false);

  async function save() {
    setBusy(true);
    try {
      await apiRequest('/auth/change-password', {
        method: 'POST',
        token,
        body: JSON.stringify({ current_password: current, new_password: next }),
      });
      setMustChange(false);
      router.replace('/');
    } catch (e: unknown) {
      Alert.alert('Password', (e as { message?: string }).message ?? 'Failed');
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg, padding: 24, justifyContent: 'center' }}>
      <Text style={{ color: colors.text, fontSize: 22, fontWeight: '700' }}>Change password</Text>
      <Text style={{ color: colors.muted, marginVertical: 8 }}>
        Required on first login. Use 10+ characters with upper, lower, number, and symbol.
      </Text>
      <PasswordField
        value={current}
        onChangeText={setCurrent}
        placeholder="Current password"
        autoComplete="current-password"
        colors={colors}
      />
      <PasswordField
        value={next}
        onChangeText={setNext}
        placeholder="New password"
        autoComplete="new-password"
        colors={colors}
      />
      <Pressable onPress={save} disabled={busy} style={{ backgroundColor: '#0b1f3a', marginTop: 20, padding: 14, borderRadius: 12 }}>
        <Text style={{ color: '#c9a227', textAlign: 'center', fontWeight: '700' }}>{busy ? '…' : 'Save'}</Text>
      </Pressable>
    </View>
  );
}
