import { Tabs } from 'expo-router';
import { useAuth } from '../../src/auth/AuthContext';
import { t } from '../../src/i18n';

export default function TabsLayout() {
  const { lang } = useAuth();
  return (
    <Tabs
      screenOptions={{
        headerStyle: { backgroundColor: '#0b1f3a' },
        headerTintColor: '#fff',
        tabBarActiveTintColor: '#c9a227',
        tabBarInactiveTintColor: '#8a93a0',
        tabBarStyle: { backgroundColor: '#0b1f3a' },
      }}
    >
      <Tabs.Screen name="id" options={{ title: t(lang, 'id') }} />
      <Tabs.Screen name="grades" options={{ title: t(lang, 'academics') }} />
      <Tabs.Screen name="study" options={{ title: t(lang, 'study') }} />
      <Tabs.Screen name="more" options={{ title: t(lang, 'more') }} />
    </Tabs>
  );
}
