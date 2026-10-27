import { router } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useAuth } from '../src/auth/AuthContext';

export default function LockScreen() {
  const { unlock, logout, colors } = useAuth();
  return (
    <View style={{ flex: 1, backgroundColor: colors.bg, justifyContent: 'center', padding: 24 }}>
      <Text style={{ color: colors.text, fontSize: 22, fontWeight: '700', textAlign: 'center' }}>Holy Face Student</Text>
      <Text style={{ color: colors.muted, textAlign: 'center', marginTop: 8 }}>Unlock with biometrics</Text>
      <Pressable
        style={{ backgroundColor: '#0b1f3a', marginTop: 32, padding: 16, borderRadius: 12 }}
        onPress={async () => {
          if (await unlock()) router.replace('/');
        }}
      >
        <Text style={{ color: '#c9a227', textAlign: 'center', fontWeight: '700' }}>Unlock</Text>
      </Pressable>
      <Pressable style={{ marginTop: 16 }} onPress={() => logout().then(() => router.replace('/login'))}>
        <Text style={{ color: colors.muted, textAlign: 'center' }}>Log out</Text>
      </Pressable>
    </View>
  );
}
