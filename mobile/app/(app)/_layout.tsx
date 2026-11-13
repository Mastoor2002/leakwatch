import { Stack } from 'expo-router';
import { useTheme } from '@/theme';

export default function AppLayout() {
  const t = useTheme();

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: t.card },
        headerTintColor: t.text,
        headerTitleStyle: { fontWeight: '700' },
        headerShadowVisible: false,
        contentStyle: { backgroundColor: t.bg },
      }}
    >
      <Stack.Screen name="index" options={{ title: 'Surfacewatch' }} />
      <Stack.Screen name="site/[id]" options={{ title: 'Site' }} />
      <Stack.Screen name="settings" options={{ title: 'Settings', presentation: 'modal' }} />
    </Stack>
  );
}
