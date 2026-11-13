import { useRouter } from 'expo-router';
import Constants from 'expo-constants';
import { useEffect, useState } from 'react';
import { ScrollView, Pressable, Text } from 'react-native';
import { session } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { Body, Button, Card, Heading, Row } from '@/components/ui';
import { useTheme } from '@/theme';

export default function Settings() {
  const t = useTheme();
  const router = useRouter();
  const { user, team, setTeam, signOut } = useAuth();
  const [server, setServer] = useState('');

  useEffect(() => {
    session.getApiUrl().then(setServer);
  }, []);

  return (
    <ScrollView contentContainerStyle={{ padding: 16, gap: 12 }}>
      <Card>
        <Heading>{user?.name}</Heading>
        <Body muted>{user?.email}</Body>
        <Body muted style={{ fontSize: 13 }}>{server}</Body>
      </Card>

      {user && user.teams.length > 1 ? (
        <Card>
          <Heading>Team</Heading>
          {user.teams.map((candidate) => (
            <Pressable
              key={candidate.id}
              onPress={() => {
                setTeam(candidate);
                router.back();
              }}
            >
              <Row style={{ paddingVertical: 6, justifyContent: 'space-between' }}>
                <Text style={{ color: t.text, fontWeight: candidate.id === team?.id ? '800' : '500' }}>{candidate.name}</Text>
                <Text style={{ color: t.muted }}>{candidate.role}</Text>
              </Row>
            </Pressable>
          ))}
        </Card>
      ) : null}

      <Button
        label="Sign out"
        variant="danger"
        onPress={async () => {
          await signOut();
          router.replace('/login');
        }}
      />

      <Body muted style={{ textAlign: 'center', fontSize: 12 }}>
        Leakwatch mobile {Constants.expoConfig?.version ?? '1.0.0'} · open source
      </Body>
    </ScrollView>
  );
}
