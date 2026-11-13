import { useEffect, useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ApiError, defaultApiUrl, session } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { Body, Button, ErrorBox, Title } from '@/components/ui';
import { useTheme } from '@/theme';

export default function Login() {
  const t = useTheme();
  const { signIn } = useAuth();
  const [apiUrl, setApiUrl] = useState(defaultApiUrl);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    session.getApiUrl().then(setApiUrl);
  }, []);

  async function submit() {
    setBusy(true);
    setError(null);

    try {
      await signIn(email.trim(), password, apiUrl);
    } catch (e) {
      if (e instanceof ApiError) {
        setError(e.errors.email?.[0] ?? e.message);
      } else {
        setError('Something went wrong. Please try again.');
      }
    } finally {
      setBusy(false);
    }
  }

  const input = [styles.input, { color: t.text, borderColor: t.border, backgroundColor: t.card }];

  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: t.bg }}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1 }}>
        <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
          <View style={styles.brand}>
            <View style={[styles.logo, { backgroundColor: t.accent }]}>
              <Text style={styles.logoText}>L</Text>
            </View>
            <Title>Surfacewatch</Title>
            <Body muted style={{ textAlign: 'center' }}>
              Security exposure monitoring for the Laravel sites you look after.
            </Body>
          </View>

          {error ? <ErrorBox message={error} /> : null}

          <Text style={[styles.label, { color: t.muted }]}>Server</Text>
          <TextInput
            style={input}
            value={apiUrl}
            onChangeText={setApiUrl}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="url"
            placeholder="https://leakwatch.example.com"
            placeholderTextColor={t.muted}
          />

          <Text style={[styles.label, { color: t.muted }]}>Email</Text>
          <TextInput
            style={input}
            value={email}
            onChangeText={setEmail}
            autoCapitalize="none"
            autoComplete="email"
            keyboardType="email-address"
            textContentType="emailAddress"
            placeholder="you@agency.com"
            placeholderTextColor={t.muted}
          />

          <Text style={[styles.label, { color: t.muted }]}>Password</Text>
          <TextInput
            style={input}
            value={password}
            onChangeText={setPassword}
            secureTextEntry
            autoComplete="password"
            textContentType="password"
            placeholder="••••••••"
            placeholderTextColor={t.muted}
            onSubmitEditing={submit}
          />

          <Button label="Sign in" onPress={submit} loading={busy} disabled={!email || !password || !apiUrl} style={{ marginTop: 16 }} />

          <Body muted style={{ textAlign: 'center', marginTop: 24, fontSize: 13 }}>
            Demo: demo@leakwatch.test / password{'\n'}against a local install. Your phone must be able to reach the server
            URL above.
          </Body>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 24, gap: 6, flexGrow: 1, justifyContent: 'center' },
  brand: { alignItems: 'center', gap: 8, marginBottom: 24 },
  logo: { width: 64, height: 64, borderRadius: 18, alignItems: 'center', justifyContent: 'center' },
  logoText: { color: '#fff', fontSize: 32, fontWeight: '900' },
  label: { fontSize: 12, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.6, marginTop: 12, marginBottom: 4 },
  input: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 10, paddingHorizontal: 14, paddingVertical: 12, fontSize: 16 },
});
