import { useRouter, Stack } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Overview, Severity, Site } from '@/lib/types';
import { Badge, Body, Card, Empty, ErrorBox, GradeChip, Heading, Loading, Row, SeverityBadge, timeAgo } from '@/components/ui';
import { severityColor, useTheme } from '@/theme';

const SEVERITIES: Severity[] = ['critical', 'high', 'medium', 'low'];

export default function Home() {
  const t = useTheme();
  const router = useRouter();
  const { team, user } = useAuth();
  const [overview, setOverview] = useState<Overview | null>(null);
  const [sites, setSites] = useState<Site[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!team) return;
    try {
      const [o, s] = await Promise.all([api.overview(team.slug), api.sites(team.slug)]);
      setOverview(o);
      setSites(s.sites);
      setError(null);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Could not load your sites.');
    }
  }, [team]);

  useEffect(() => {
    load();
  }, [load]);

  async function onRefresh() {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }

  if (!team) {
    return <Empty title="No team yet" body="Create a team on the web app, then sign in again." />;
  }

  return (
    <>
      <Stack.Screen
        options={{
          title: team.name,
          headerRight: () => (
            <Pressable onPress={() => router.push('/(app)/settings')} hitSlop={12} accessibilityLabel="Settings">
              <Text style={{ color: t.accent, fontWeight: '700' }}>{user?.initials ?? 'Me'}</Text>
            </Pressable>
          ),
        }}
      />
      <FlatList
        data={sites ?? []}
        keyExtractor={(site) => String(site.id)}
        contentContainerStyle={{ padding: 16, gap: 10 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={t.accent} />}
        ListHeaderComponent={
          <View style={{ gap: 12, marginBottom: 6 }}>
            {error ? <ErrorBox message={error} onRetry={load} /> : null}
            {overview ? <Summary overview={overview} /> : null}
            <Heading>Sites</Heading>
          </View>
        }
        ListEmptyComponent={
          sites === null && !error ? (
            <Loading />
          ) : (
            <Empty title="No sites yet" body="Add your first site on the web app, then pull to refresh here." />
          )
        }
        renderItem={({ item }) => <SiteRow site={item} onPress={() => router.push({ pathname: '/(app)/site/[id]', params: { id: String(item.id) } })} />}
      />
    </>
  );
}

function Summary({ overview }: { overview: Overview }) {
  const t = useTheme();
  const { stats } = overview;

  return (
    <Card style={{ gap: 14 }}>
      <Row style={{ justifyContent: 'space-between' }}>
        <View>
          <Text style={[styles.big, { color: t.text }]}>{stats.open_findings}</Text>
          <Body muted>open findings across {stats.sites} {stats.sites === 1 ? 'site' : 'sites'}</Body>
        </View>
        <View style={{ alignItems: 'flex-end' }}>
          <Text style={[styles.big, { color: t.text }]}>{stats.average_score ?? '–'}</Text>
          <Body muted>avg score</Body>
        </View>
      </Row>
      <Row style={{ flexWrap: 'wrap' }}>
        {SEVERITIES.map((severity) => (
          <Badge key={severity} label={`${stats.open_by_severity[severity] ?? 0} ${severity}`} color={severityColor(severity)} />
        ))}
      </Row>
      <Row style={{ justifyContent: 'space-between' }}>
        {(['A', 'B', 'C', 'D', 'F'] as const).map((grade) => (
          <View key={grade} style={{ alignItems: 'center', gap: 4 }}>
            <GradeChip grade={grade} size={32} />
            <Text style={{ color: t.muted, fontWeight: '700' }}>{stats.grades[grade] ?? 0}</Text>
          </View>
        ))}
      </Row>
    </Card>
  );
}

function SiteRow({ site, onPress }: { site: Site; onPress: () => void }) {
  const t = useTheme();
  const worst = SEVERITIES.find((s) => (site.open_by_severity?.[s] ?? 0) > 0);

  return (
    <Pressable onPress={onPress} accessibilityRole="button">
      <Card>
        <Row>
          <GradeChip grade={site.grade} />
          <View style={{ flex: 1 }}>
            <Text style={[styles.host, { color: t.text }]} numberOfLines={1}>
              {site.host}
            </Text>
            <Body muted numberOfLines={1} style={{ fontSize: 13 }}>
              {site.client?.name ?? 'No client'} · scanned {timeAgo(site.last_scanned_at)}
            </Body>
          </View>
          <View style={{ alignItems: 'flex-end', gap: 4 }}>
            {worst ? <SeverityBadge severity={worst} /> : site.last_scanned_at ? <Badge label="CLEAN" color="#16a34a" /> : null}
            <Body muted style={{ fontSize: 12 }}>
              {site.open_findings} open
            </Body>
          </View>
        </Row>
        {!site.is_verified ? <Badge label="UNVERIFIED · limited checks" color="#d97706" style={{ marginTop: 6 }} /> : null}
        {site.active_scan ? <Badge label={`SCANNING ${site.active_scan.progress}%`} color={t.accent} style={{ marginTop: 6 }} /> : null}
      </Card>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  big: { fontSize: 32, fontWeight: '800', letterSpacing: -1 },
  host: { fontSize: 16, fontWeight: '700' },
});
