import { Stack, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { AuthProvider, useAuth } from '@/lib/auth';
import { Loading } from '@/components/ui';
import { useTheme } from '@/theme';

function Gate() {
  const { ready, user } = useAuth();
  const segments = useSegments();
  const router = useRouter();
  const t = useTheme();

  useEffect(() => {
    if (!ready) return;
    const inApp = segments[0] === '(app)';
    if (!user && inApp) router.replace('/login');
    if (user && !inApp) router.replace('/(app)');
  }, [ready, user, segments, router]);

  if (!ready) return <Loading />;

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: t.card },
        headerTintColor: t.text,
        headerTitleStyle: { fontWeight: '700' },
        contentStyle: { backgroundColor: t.bg },
      }}
    >
      <Stack.Screen name="login" options={{ headerShown: false }} />
      <Stack.Screen name="(app)" options={{ headerShown: false }} />
    </Stack>
  );
}

export default function RootLayout() {
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <AuthProvider>
        <StatusBar style="auto" />
        <Gate />
      </AuthProvider>
    </GestureHandlerRootView>
  );
}
