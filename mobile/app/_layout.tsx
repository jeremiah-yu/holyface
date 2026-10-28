import { Stack, type ErrorBoundaryProps } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { Pressable, Text, View } from 'react-native';
import { AuthProvider, useAuth } from '../src/auth/AuthContext';
import { StudyChatbot } from '../src/components/StudyChatbot';

export function ErrorBoundary({ error, retry }: ErrorBoundaryProps) {
  return (
    <View style={{ flex: 1, backgroundColor: '#0b1f3a', padding: 24, justifyContent: 'center' }}>
      <Text style={{ color: '#c9a227', fontSize: 20, fontWeight: '700' }}>Something went wrong</Text>
      <Text style={{ color: '#fff', marginTop: 12 }}>{error?.message ?? 'Unknown error'}</Text>
      <Pressable onPress={retry} style={{ marginTop: 20, backgroundColor: '#c9a227', padding: 14, borderRadius: 12 }}>
        <Text style={{ color: '#0b1f3a', textAlign: 'center', fontWeight: '700' }}>Try again</Text>
      </Pressable>
    </View>
  );
}

function Inner() {
  const { darkMode, token, ready, locked, mustChange, consentComplete } = useAuth();
  const showChat = Boolean(ready && token && !locked && !mustChange && consentComplete);
  return (
    <>
      <StatusBar style={darkMode ? 'light' : 'light'} />
      <Stack
        screenOptions={{
          headerStyle: { backgroundColor: '#0b1f3a' },
          headerTintColor: '#fff',
          headerTitleStyle: { fontWeight: '700' },
        }}
      />
      {showChat ? <StudyChatbot /> : null}
    </>
  );
}

export default function RootLayout() {
  return (
    <AuthProvider>
      <Inner />
    </AuthProvider>
  );
}
