import { type ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View, type StyleProp, type TextStyle, type ViewStyle } from 'react-native';
import { gradeColor, severityColor, useTheme } from '@/theme';

export function Card({ children, style }: { children: ReactNode; style?: StyleProp<ViewStyle> }) {
  const t = useTheme();
  return <View style={[styles.card, { backgroundColor: t.card, borderColor: t.border }, style]}>{children}</View>;
}

export function Title({ children, style }: { children: ReactNode; style?: StyleProp<TextStyle> }) {
  const t = useTheme();
  return <Text style={[styles.title, { color: t.text }, style]}>{children}</Text>;
}

export function Heading({ children, style }: { children: ReactNode; style?: StyleProp<TextStyle> }) {
  const t = useTheme();
  return <Text style={[styles.heading, { color: t.text }, style]}>{children}</Text>;
}

export function Body({
  children,
  muted,
  style,
  numberOfLines,
}: {
  children: ReactNode;
  muted?: boolean;
  style?: StyleProp<TextStyle>;
  numberOfLines?: number;
}) {
  const t = useTheme();
  return (
    <Text numberOfLines={numberOfLines} style={[styles.body, { color: muted ? t.muted : t.text }, style]}>
      {children}
    </Text>
  );
}

export function Mono({ children }: { children: ReactNode }) {
  const t = useTheme();
  return (
    <Text selectable style={[styles.mono, { color: t.text, backgroundColor: t.dark ? '#1e293b' : '#f1f5f9' }]}>
      {children}
    </Text>
  );
}

export function Button({
  label,
  onPress,
  variant = 'primary',
  loading,
  disabled,
  style,
}: {
  label: string;
  onPress: () => void;
  variant?: 'primary' | 'secondary' | 'danger';
  loading?: boolean;
  disabled?: boolean;
  style?: StyleProp<ViewStyle>;
}) {
  const t = useTheme();
  const bg = variant === 'primary' ? t.accent : variant === 'danger' ? t.danger : 'transparent';
  const fg = variant === 'secondary' ? t.text : '#fff';

  return (
    <Pressable
      accessibilityRole="button"
      disabled={disabled || loading}
      onPress={onPress}
      style={({ pressed }) => [
        styles.button,
        { backgroundColor: bg, borderColor: variant === 'secondary' ? t.border : bg, opacity: pressed || disabled ? 0.7 : 1 },
        style,
      ]}
    >
      {loading ? <ActivityIndicator color={fg} /> : <Text style={[styles.buttonLabel, { color: fg }]}>{label}</Text>}
    </Pressable>
  );
}

export function Badge({ label, color, style }: { label: string; color: string; style?: StyleProp<ViewStyle> }) {
  return (
    <View style={[styles.badge, { backgroundColor: `${color}22`, borderColor: `${color}66` }, style]}>
      <Text style={[styles.badgeLabel, { color }]}>{label}</Text>
    </View>
  );
}

export function SeverityBadge({ severity }: { severity: string }) {
  return <Badge label={severity.toUpperCase()} color={severityColor(severity)} />;
}

export function GradeChip({ grade, size = 44 }: { grade: string | null | undefined; size?: number }) {
  const color = gradeColor(grade);

  return (
    <View style={{ width: size, height: size, borderRadius: size * 0.25, backgroundColor: color, alignItems: 'center', justifyContent: 'center' }}>
      <Text style={{ color: '#fff', fontWeight: '800', fontSize: size * 0.5 }}>{grade ?? '–'}</Text>
    </View>
  );
}

export function Row({ children, style }: { children: ReactNode; style?: StyleProp<ViewStyle> }) {
  return <View style={[styles.row, style]}>{children}</View>;
}

export function Empty({ title, body }: { title: string; body?: string }) {
  const t = useTheme();
  return (
    <View style={styles.empty}>
      <Text style={[styles.heading, { color: t.text, textAlign: 'center' }]}>{title}</Text>
      {body ? <Text style={[styles.body, { color: t.muted, textAlign: 'center', marginTop: 6 }]}>{body}</Text> : null}
    </View>
  );
}

export function Loading() {
  const t = useTheme();
  return (
    <View style={styles.empty}>
      <ActivityIndicator color={t.accent} size="large" />
    </View>
  );
}

export function ErrorBox({ message, onRetry }: { message: string; onRetry?: () => void }) {
  const t = useTheme();
  return (
    <View style={[styles.card, { borderColor: t.danger, backgroundColor: `${t.danger}11` }]}>
      <Text style={[styles.body, { color: t.danger }]}>{message}</Text>
      {onRetry ? <Button label="Try again" variant="secondary" onPress={onRetry} style={{ marginTop: 10 }} /> : null}
    </View>
  );
}

export function timeAgo(iso: string | null | undefined): string {
  if (!iso) return 'never';
  const seconds = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
  if (seconds < 60) return 'just now';
  const minutes = Math.round(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.round(hours / 24);
  return days < 30 ? `${days}d ago` : new Date(iso).toLocaleDateString();
}

const styles = StyleSheet.create({
  card: { borderWidth: StyleSheet.hairlineWidth, borderRadius: 14, padding: 14, gap: 6 },
  title: { fontSize: 26, fontWeight: '800', letterSpacing: -0.5 },
  heading: { fontSize: 17, fontWeight: '700' },
  body: { fontSize: 15, lineHeight: 21 },
  mono: { fontFamily: 'monospace', fontSize: 12, padding: 8, borderRadius: 8, overflow: 'hidden' },
  button: { paddingVertical: 12, paddingHorizontal: 16, borderRadius: 10, alignItems: 'center', borderWidth: StyleSheet.hairlineWidth },
  buttonLabel: { fontSize: 15, fontWeight: '700' },
  badge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999, borderWidth: StyleSheet.hairlineWidth, alignSelf: 'flex-start' },
  badgeLabel: { fontSize: 11, fontWeight: '800', letterSpacing: 0.5 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  empty: { padding: 32, alignItems: 'center', justifyContent: 'center' },
});
