import { Redirect } from 'expo-router';
import { ActivityIndicator, View } from 'react-native';
import { useAuth } from '../src/auth/AuthContext';

export default function Gate() {
  const { ready, token, user, locked, mustChange, consentComplete, colors } = useAuth();
  if (!ready) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.navy === '#c9a227' ? '#0a1220' : '#0b1f3a', justifyContent: 'center' }}>
        <ActivityIndicator color="#c9a227" />
      </View>
    );
  }
  if (!token || !user) return <Redirect href="/login" />;
  if (locked) return <Redirect href="/lock" />;
  if (mustChange) return <Redirect href="/change-password" />;
  if (!consentComplete) return <Redirect href="/consent" />;
  return <Redirect href="/(tabs)/id" />;
}
