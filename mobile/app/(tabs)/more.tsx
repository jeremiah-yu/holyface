import { router } from 'expo-router';
import { Alert, Pressable, ScrollView, Switch, Text, View } from 'react-native';
import { useAuth } from '../../src/auth/AuthContext';
import { t } from '../../src/i18n';

export default function MoreScreen() {
  const { user, logout, lang, setLang, darkMode, setDarkMode, notifications, setNotifications, biometricEnabled, biometricReady, enableBiometric, colors } =
    useAuth();

  return (
    <ScrollView contentContainerStyle={{ padding: 16, backgroundColor: colors.bg, flexGrow: 1 }}>
      <Text style={{ color: colors.text, fontSize: 20, fontWeight: '700' }}>{user?.full_name}</Text>
      <Text style={{ color: colors.muted }}>{user?.username}</Text>
      <Row label="Profile" onPress={() => router.push('/profile')} colors={colors} />
      <Row label={t(lang, 'changePassword')} onPress={() => router.push('/change-password')} colors={colors} />
      <Row label={t(lang, 'privacyHistory')} onPress={() => router.push('/consent-history')} colors={colors} />
      <Row label="Terms of Use" onPress={() => router.push({ pathname: '/legal', params: { doc: 'terms' } })} colors={colors} />
      <Row label="Privacy Policy" onPress={() => router.push({ pathname: '/legal', params: { doc: 'privacy' } })} colors={colors} />
      <Row label="AI Use Policy" onPress={() => router.push({ pathname: '/legal', params: { doc: 'ai_policy' } })} colors={colors} />
      <View style={row}>
        <Text style={{ color: colors.text }}>{t(lang, 'language')}</Text>
        <Pressable onPress={() => setLang(lang === 'en' ? 'fil' : 'en')}>
          <Text style={{ color: colors.link, fontWeight: '700' }}>{lang === 'en' ? 'English' : 'Filipino'}</Text>
        </Pressable>
      </View>
      <View style={row}>
        <Text style={{ color: colors.text }}>{t(lang, 'darkMode')}</Text>
        <Switch value={darkMode} onValueChange={setDarkMode} />
      </View>
      <View style={row}>
        <Text style={{ color: colors.text }}>{t(lang, 'notifications')}</Text>
        <Switch value={notifications} onValueChange={setNotifications} />
      </View>
      <View style={row}>
        <Text style={{ color: colors.text }}>{t(lang, 'biometric')}</Text>
        <Switch
          value={biometricEnabled}
          onValueChange={async (v) => {
            try {
              await enableBiometric(v);
              if (v && !biometricReady) {
                Alert.alert(
                  'Biometrics',
                  'Biometric login is on. Sign in with your password once. After that, the login screen can use fingerprint or face.'
                );
              }
            } catch (e: unknown) {
              Alert.alert('Biometrics', (e as Error).message);
            }
          }}
        />
      </View>
      <Pressable
        onPress={() => logout().then(() => router.replace('/login'))}
        style={{ marginTop: 24, padding: 14, backgroundColor: '#0b1f3a', borderRadius: 12 }}
      >
        <Text style={{ color: '#c9a227', textAlign: 'center', fontWeight: '700' }}>Log out</Text>
      </Pressable>
    </ScrollView>
  );
}

function Row({ label, onPress, colors }: { label: string; onPress: () => void; colors: { text: string; link: string } }) {
  return (
    <Pressable onPress={onPress} style={row}>
      <Text style={{ color: colors.text }}>{label}</Text>
      <Text style={{ color: colors.link }}>→</Text>
    </Pressable>
  );
}

const row = { flexDirection: 'row' as const, justifyContent: 'space-between' as const, alignItems: 'center' as const, paddingVertical: 14 };
