import { router } from 'expo-router';
import { useState } from 'react';
import { Alert, Image, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { useAuth } from '../src/auth/AuthContext';
import { t } from '../src/i18n';

export default function LoginScreen() {
  const { login, lang, colors } = useAuth();
  const [id, setId] = useState('s001');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);

  async function onLogin() {
    setBusy(true);
    try {
      await login(id.trim(), password);
      router.replace('/');
    } catch (e: unknown) {
      Alert.alert('Login', (e as { message?: string }).message ?? 'Failed');
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={[styles.wrap, { backgroundColor: colors.bg }]}>
      <Image
        source={{ uri: 'https://www.holyface.school/assets/img/branding/hf-logo.png' }}
        style={styles.logo}
      />
      <Text style={[styles.title, { color: colors.text }]}>Holy Face Student</Text>
      <Text style={{ color: colors.muted, marginBottom: 16 }}>Senior High · Digital ID &amp; study tools</Text>
      <Text style={[styles.label, { color: colors.text }]}>{t(lang, 'studentId')}</Text>
      <TextInput
        style={[styles.input, { color: colors.text, borderColor: colors.border, backgroundColor: colors.card }]}
        autoCapitalize="none"
        value={id}
        onChangeText={setId}
        placeholder="s001 or 12-digit LRN"
        placeholderTextColor={colors.muted}
      />
      <Text style={[styles.label, { color: colors.text }]}>{t(lang, 'password')}</Text>
      <TextInput
        style={[styles.input, { color: colors.text, borderColor: colors.border, backgroundColor: colors.card }]}
        secureTextEntry
        value={password}
        onChangeText={setPassword}
      />
      <Pressable style={styles.btn} onPress={onLogin} disabled={busy}>
        <Text style={styles.btnText}>{busy ? '…' : t(lang, 'login')}</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flex: 1, padding: 24, justifyContent: 'center' },
  logo: { width: 72, height: 72, alignSelf: 'center', borderRadius: 36, marginBottom: 12, backgroundColor: '#fff' },
  title: { fontSize: 24, fontWeight: '700', textAlign: 'center' },
  label: { fontWeight: '600', marginTop: 12 },
  input: { borderWidth: 1, borderRadius: 8, padding: 12, marginTop: 4 },
  btn: { backgroundColor: '#0b1f3a', marginTop: 24, padding: 16, borderRadius: 12, alignItems: 'center' },
  btnText: { color: '#c9a227', fontWeight: '700' },
});
