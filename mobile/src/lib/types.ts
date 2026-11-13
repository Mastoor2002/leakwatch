export type Severity = 'critical' | 'high' | 'medium' | 'low' | 'info';

export type Team = {
  id: number;
  slug: string;
  name: string;
  role: string | null;
  alerts_enabled: boolean;
};

export type User = {
  id: number;
  name: string;
  email: string;
  initials: string;
  current_team: Team | null;
  teams: Team[];
};

export type Scan = {
  id: number;
  status: 'queued' | 'running' | 'completed' | 'failed';
  is_active: boolean;
  trigger: string;
  progress: number;
  score: number | null;
  grade: string | null;
  new_findings_count: number;
  resolved_findings_count: number;
  error: string | null;
  started_at: string | null;
  finished_at: string | null;
  duration: string | null;
  created_at: string | null;
  checks?: ScanCheck[];
};

export type ScanCheck = {
  key: string;
  name: string;
  status: 'passed' | 'failed' | 'skipped' | 'error' | null;
  duration_ms: number | null;
  error: string | null;
};

export type Finding = {
  id: number;
  check_key: string;
  severity: Severity;
  severity_rank: number;
  title: string;
  summary: string;
  evidence: Record<string, unknown>;
  remediation: string;
  status: 'open' | 'resolved' | 'ignored';
  ignored_reason: string | null;
  first_seen_at: string | null;
  resolved_at: string | null;
};

export type PackageFinding = {
  id: number;
  package_name: string;
  installed_version: string;
  is_dev: boolean;
  status: string;
  advisory?: {
    id: string;
    title: string;
    cve: string | null;
    link: string | null;
    severity: string | null;
    affected_versions: string | null;
  };
  first_seen_at: string | null;
};

export type Site = {
  id: number;
  url: string;
  host: string;
  client?: { id: number; name: string };
  is_laravel: boolean | null;
  is_verified: boolean;
  verification: { token: string; dns_record: string; file_path: string };
  score: number | null;
  grade: string | null;
  scan_frequency: string;
  last_scanned_at: string | null;
  open_findings: number;
  open_by_severity: Record<Severity, number>;
  active_scan: Scan | null;
  agent: { connected: boolean; last_seen_at: string | null };
  findings?: Finding[];
  package_findings?: PackageFinding[];
  last_scan?: Scan | null;
  created_at: string | null;
};

export type Overview = {
  team: { id: number; slug: string; name: string };
  stats: {
    sites: number;
    verified_sites: number;
    scanned_sites: number;
    open_findings: number;
    open_by_severity: Record<Severity, number>;
    grades: Record<'A' | 'B' | 'C' | 'D' | 'F', number>;
    average_score: number | null;
  };
  needs_attention: Site[];
};
