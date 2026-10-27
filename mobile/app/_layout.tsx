import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { AuthProvider, useAuth } from '../src/auth/AuthContext';

function Inner() {
  const { darkMode } = useAuth();
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
