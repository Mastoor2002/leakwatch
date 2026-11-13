import { useColorScheme } from 'react-native';

export const palette = {
  slate950: '#020617',
  slate900: '#0f172a',
  slate800: '#1e293b',
  slate700: '#334155',
  slate500: '#64748b',
  slate400: '#94a3b8',
  slate200: '#e2e8f0',
  slate100: '#f1f5f9',
  white: '#ffffff',
  red: '#dc2626',
  orange: '#ea580c',
  amber: '#d97706',
  sky: '#0284c7',
  green: '#16a34a',
  indigo: '#4f46e5',
};

export type Theme = {
  dark: boolean;
  bg: string;
  card: string;
  border: string;
  text: string;
  muted: string;
  accent: string;
  danger: string;
};

export function useTheme(): Theme {
  const dark = useColorScheme() === 'dark';

  return dark
    ? {
        dark,
        bg: palette.slate950,
        card: palette.slate900,
        border: palette.slate800,
        text: palette.slate100,
        muted: palette.slate400,
        accent: '#818cf8',
        danger: '#f87171',
      }
    : {
        dark,
        bg: palette.slate100,
        card: palette.white,
        border: palette.slate200,
        text: palette.slate900,
        muted: palette.slate500,
        accent: palette.indigo,
        danger: palette.red,
      };
}

export function severityColor(severity: string): string {
  switch (severity) {
    case 'critical':
      return palette.red;
    case 'high':
      return palette.orange;
    case 'medium':
      return palette.amber;
    case 'low':
      return palette.sky;
    default:
      return palette.slate500;
  }
}

export function gradeColor(grade: string | null | undefined): string {
  switch (grade) {
    case 'A':
      return palette.green;
    case 'B':
      return '#65a30d';
    case 'C':
      return palette.amber;
    case 'D':
      return palette.orange;
    case 'F':
      return palette.red;
    default:
      return palette.slate500;
  }
}
