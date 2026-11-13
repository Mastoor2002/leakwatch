import { Stack, useLocalSearchParams } from 'expo-router';
import * as Haptics from 'expo-haptics';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, Linking, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Finding, Scan, Site } from '@/lib/types';
import { Badge, Body, Button, Card, Empty, ErrorBox, GradeChip, Heading, Loading, Mono, Row, SeverityBadge, timeAgo } from '@/components/ui';
import { gradeColor, useTheme } from '@/theme';

type Tab = 'findings' | 'checks' | 'packages';

export default function SiteDetail() {
  const t = useTheme();
  const { id } = useLocalSearchParams<{ id: string }>();
  const siteId = Number(id);
  const { team } = useAuth();
  const [site, setSite] = useState<Site | null>(null);
  const [scan, setScan] = useState<Scan | null>(null);
  const [tab, setTab] = useState<Tab>('findings');
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [starting, setStarting] = useState(false);
  const [verifying, setVerifying] = useState(false);
  const polling = useRef(false);

  const load = useCallback(async () => {
    if (!team) return;
    try {
      const { site } = await api.site(team.slug, siteId);
      setSite(site);
      setScan(site.active_scan ?? site.last_scan ?? null);
      setError(null);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Could not load this site.');
    }
  }, [team, siteId]);

  useEffect(() => {
    load();
  }, [load]);

  // While a scan is running, poll its status. With the inline driver each poll
  // also advances the scan by one check, so this loop is what drives it.
  useEffect(() => {
    if (!team || !scan?.is_active || polling.current) return;
    polling.current = true;
    let cancelled = false;

    (async () => {
      while (!cancelled) {
        try {
          const status = await api.scanStatus(team.slug, siteId);
          if (cancelled) break;
          setScan(status.scan);
          if (!status.scan?.is_active) {
            await load();
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => {});
            break;
          }
        } catch (e) {
          if (!cancelled) setError(e instanceof ApiError ? e.message : 'Lost connection while scanning.');
          break;
        }
        await new Promise((resolve) => setTimeout(resolve, 900));
      }
      polling.current = false;
    })();

    return () => {
      cancelled = true;
      polling.current = false;
    };
  }, [team, siteId, scan?.is_active, load]);

  async function startScan() {
    if (!team) return;
    setStarting(true);
    setError(null);
    try {
      const { scan } = await api.startScan(team.slug, siteId);
      setScan(scan);
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Could not start the scan.');
    } finally {
      setStarting(false);
    }
  }

  async function verify() {
    if (!team) return;
    setVerifying(true);
    try {
      const result = await api.verify(team.slug, siteId);
      Alert.alert(result.verified ? 'Verified' : 'Not verified yet', result.message);
      if (result.verified) await load();
    } catch (e) {
      Alert.alert('Verification failed', e instanceof ApiError ? e.message : 'Try again in a moment.');
    } finally {
      setVerifying(false);
    }
  }

  async function onRefresh() {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  }

  if (!site) {
    return error ? <ErrorBox message={error} onRetry={load} /> : <Loading />;
  }

  const findings = (site.findings ?? []).filter((f) => f.status === 'open');
  const closed = (site.findings ?? []).filter((f) => f.status !== 'open');
  const scanning = !!scan?.is_active;

  return (
    <>
      <Stack.Screen options={{ title: site.host }} />
      <ScrollView
        contentContainerStyle={{ padding: 16, gap: 12 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={t.accent} />}
      >
        {error ? <ErrorBox message={error} /> : null}

        <Card>
          <Row>
            <GradeChip grade={site.grade} size={64} />
            <View style={{ flex: 1, gap: 2 }}>
              <Text style={[styles.score, { color: gradeColor(site.grade) }]}>
                {site.score ?? '–'}
                <Text style={{ fontSize: 16, color: t.muted }}> / 100</Text>
              </Text>
              <Body muted style={{ fontSize: 13 }}>
                Last scan {timeAgo(site.last_scanned_at)}
              </Body>
              <Row style={{ flexWrap: 'wrap', gap: 6 }}>
                {site.is_verified ? <Badge label="VERIFIED" color="#16a34a" /> : <Badge label="UNVERIFIED" color="#d97706" />}
                {site.is_laravel ? <Badge label="LARAVEL" color="#dc2626" /> : null}
                {site.agent.connected ? <Badge label="AGENT" color={t.accent} /> : null}
              </Row>
            </View>
          </Row>

          {scanning ? (
            <View style={{ marginTop: 10, gap: 6 }}>
              <View style={[styles.track, { backgroundColor: t.border }]}>
                <View style={[styles.fill, { backgroundColor: t.accent, width: `${scan?.progress ?? 0}%` }]} />
              </View>
              <Body muted style={{ fontSize: 13 }}>
                Scanning… {scan?.progress ?? 0}%
              </Body>
            </View>
          ) : null}

          <Row style={{ marginTop: 10 }}>
            <Button label={scanning ? 'Scanning…' : 'Scan now'} onPress={startScan} loading={starting} disabled={scanning} style={{ flex: 1 }} />
            <Button label="Open site" variant="secondary" onPress={() => Linking.openURL(site.url)} />
          </Row>
        </Card>

        {!site.is_verified ? (
          <Card>
            <Heading>Verify ownership</Heading>
            <Body muted>
              Until you prove you own this site, Leakwatch only runs passive checks (TLS, headers, redirects, email auth). Add this DNS TXT
              record, or serve the token at the path below, then verify.
            </Body>
            <Mono>{site.verification.dns_record}</Mono>
            <Mono>{site.verification.file_path}</Mono>
            <Button label="Check verification" variant="secondary" onPress={verify} loading={verifying} />
          </Card>
        ) : null}

        <Row style={{ gap: 8 }}>
          {(['findings', 'checks', 'packages'] as Tab[]).map((name) => (
            <Pressable
              key={name}
              onPress={() => setTab(name)}
              style={[styles.tab, { backgroundColor: tab === name ? t.accent : t.card, borderColor: t.border }]}
            >
              <Text style={{ color: tab === name ? '#fff' : t.text, fontWeight: '700', textTransform: 'capitalize' }}>
                {name}
                {name === 'findings' ? ` (${findings.length})` : name === 'packages' ? ` (${site.package_findings?.length ?? 0})` : ''}
              </Text>
            </Pressable>
          ))}
        </Row>

        {tab === 'findings' ? (
          <>
            {findings.length === 0 ? (
              <Empty
                title={site.last_scanned_at ? 'No open findings' : 'Not scanned yet'}
                body={site.last_scanned_at ? 'Everything Leakwatch checked looks fine.' : 'Run a scan to see what is exposed.'}
              />
            ) : (
              findings.map((finding) => <FindingCard key={finding.id} finding={finding} />)
            )}
            {closed.length ? <Body muted style={{ textAlign: 'center' }}>{closed.length} resolved or ignored finding(s) not shown</Body> : null}
          </>
        ) : null}

        {tab === 'checks' ? (
          scan?.checks?.length ? (
            scan.checks.map((check) => (
              <Card key={check.key}>
                <Row style={{ justifyContent: 'space-between' }}>
                  <Text style={{ color: t.text, fontWeight: '600', flex: 1 }}>{check.name}</Text>
                  <Badge
                    label={(check.status ?? 'pending').toUpperCase()}
                    color={check.status === 'passed' ? '#16a34a' : check.status === 'failed' ? '#dc2626' : check.status === 'error' ? '#ea580c' : '#64748b'}
                  />
                </Row>
                {check.error ? <Body muted style={{ fontSize: 13 }}>{check.error}</Body> : null}
              </Card>
            ))
          ) : (
            <Empty title="No scan data" body="Run a scan to see each check's result." />
          )
        ) : null}

        {tab === 'packages' ? (
          site.package_findings?.length ? (
            site.package_findings.map((pf) => (
              <Card key={pf.id}>
                <Row style={{ justifyContent: 'space-between' }}>
                  <Text style={{ color: t.text, fontWeight: '700', flex: 1 }}>
                    {pf.package_name} {pf.installed_version}
                  </Text>
                  {pf.advisory?.severity ? <SeverityBadge severity={pf.advisory.severity.toLowerCase()} /> : null}
                </Row>
                <Body>{pf.advisory?.title}</Body>
                {pf.advisory?.cve ? <Body muted>{pf.advisory.cve}</Body> : null}
                {pf.advisory?.affected_versions ? <Mono>affected: {pf.advisory.affected_versions}</Mono> : null}
              </Card>
            ))
          ) : (
            <Empty
              title={site.agent.connected ? 'No vulnerable packages' : 'Agent not connected'}
              body={site.agent.connected ? 'Composer packages match no known advisories.' : 'Install the Leakwatch agent on this site to check Composer packages.'}
            />
          )
        ) : null}
      </ScrollView>
    </>
  );
}

function FindingCard({ finding }: { finding: Finding }) {
  const t = useTheme();
  const [open, setOpen] = useState(false);
  const evidence = Object.entries(finding.evidence ?? {});

  return (
    <Pressable onPress={() => setOpen((o) => !o)} accessibilityRole="button">
      <Card>
        <Row style={{ justifyContent: 'space-between' }}>
          <SeverityBadge severity={finding.severity} />
          <Text style={{ color: t.muted, fontSize: 12 }}>{open ? 'Hide' : 'Details'}</Text>
        </Row>
        <Text style={{ color: t.text, fontSize: 16, fontWeight: '700' }}>{finding.title}</Text>
        <Body muted>{finding.summary}</Body>

        {open ? (
          <View style={{ gap: 8, marginTop: 6 }}>
            {evidence.map(([key, value]) => (
              <View key={key}>
                <Text style={{ color: t.muted, fontSize: 11, fontWeight: '700', textTransform: 'uppercase' }}>{key.replace(/_/g, ' ')}</Text>
                <Mono>{Array.isArray(value) ? value.join(', ') : String(value)}</Mono>
              </View>
            ))}
            <Text style={{ color: t.text, fontWeight: '700', marginTop: 4 }}>How to fix it</Text>
            <Body>{finding.remediation}</Body>
          </View>
        ) : null}
      </Card>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  score: { fontSize: 34, fontWeight: '800', letterSpacing: -1 },
  track: { height: 8, borderRadius: 4, overflow: 'hidden' },
  fill: { height: 8, borderRadius: 4 },
  tab: { paddingHorizontal: 12, paddingVertical: 8, borderRadius: 999, borderWidth: StyleSheet.hairlineWidth },
});
