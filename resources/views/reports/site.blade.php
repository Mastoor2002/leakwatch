<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Security report – {{ $site->host }}</title>
    <style>
        :root { --ink: #18181b; --muted: #71717a; --line: #e4e4e7; }
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); margin: 0; background: #f4f4f5; }
        .page { max-width: 860px; margin: 32px auto; background: #fff; padding: 48px; border-radius: 12px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid var(--ink); padding-bottom: 20px; }
        h1 { font-size: 26px; margin: 0 0 4px; }
        h2 { font-size: 18px; margin: 32px 0 12px; }
        h3 { font-size: 15px; margin: 0; }
        .muted { color: var(--muted); font-size: 13px; }
        .grade { width: 84px; height: 84px; border-radius: 14px; color: #fff; font-size: 44px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        .g-A { background: #16a34a; } .g-B { background: #65a30d; } .g-C { background: #ca8a04; } .g-D { background: #ea580c; } .g-F { background: #dc2626; } .g- { background: #a1a1aa; }
        .summary { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 20px; }
        .summary div { border: 1px solid var(--line); border-radius: 8px; padding: 10px; text-align: center; }
        .summary strong { display: block; font-size: 22px; }
        .finding { border: 1px solid var(--line); border-radius: 10px; padding: 16px; margin-bottom: 12px; page-break-inside: avoid; }
        .pill { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; color: #fff; margin-right: 8px; text-transform: uppercase; }
        .s-critical { background: #dc2626; } .s-high { background: #ea580c; } .s-medium { background: #ca8a04; } .s-low { background: #0284c7; } .s-info { background: #71717a; }
        .fix { white-space: pre-line; font-size: 13px; background: #fafafa; border-left: 3px solid var(--ink); padding: 10px 12px; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .toolbar { max-width: 860px; margin: 16px auto 0; text-align: right; }
        .toolbar button { background: var(--ink); color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; font-size: 14px; cursor: pointer; }
        footer { margin-top: 40px; font-size: 12px; color: var(--muted); border-top: 1px solid var(--line); padding-top: 12px; }
        @media print { body { background: #fff; } .page { margin: 0; padding: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>

    <div class="page">
        <header>
            <div>
                <div class="muted">Website security report · {{ now()->toFormattedDateString() }}</div>
                <h1>{{ $site->host }}</h1>
                <div class="muted">
                    Client: {{ $site->client->name }} · Prepared by {{ $site->team->name }}
                    @if ($site->lastScan?->finished_at) · Last scan {{ $site->lastScan->finished_at->toDayDateTimeString() }} @endif
                </div>
            </div>
            <div class="grade g-{{ $site->grade }}">{{ $site->grade ?? '–' }}</div>
        </header>

        <p>
            Surfacewatch checked <strong>{{ $site->url }}</strong> from the outside for common, high-impact mistakes:
            exposed configuration and log files, debug mode, public admin dashboards, TLS, security headers, cookie
            settings and email spoofing protection.
            @if ($site->score !== null) The site currently scores <strong>{{ $site->score }}/100</strong>. @endif
            @unless ($site->isVerified())
                <em>Ownership has not been verified yet, so only passive checks were run.</em>
            @endunless
        </p>

        <div class="summary">
            @foreach ($counts as $severity => $count)
                <div><strong>{{ $count }}</strong><span class="muted">{{ ucfirst($severity) }}</span></div>
            @endforeach
        </div>

        <h2>Open issues</h2>
        @forelse ($findings as $finding)
            <div class="finding">
                <h3><span class="pill s-{{ $finding->severity->value }}">{{ $finding->severity->label() }}</span>{{ $finding->title }}</h3>
                <p style="font-size: 14px; margin: 8px 0 0;">{{ $finding->summary }}</p>
                <div class="fix">{{ $finding->remediation }}</div>
            </div>
        @empty
            <p>No open issues. 🎉</p>
        @endforelse

        @if ($packages->isNotEmpty())
            <h2>Vulnerable software packages</h2>
            <table>
                <thead><tr><th>Package</th><th>Installed</th><th>Advisory</th><th>Severity</th></tr></thead>
                <tbody>
                @foreach ($packages as $package)
                    <tr>
                        <td>{{ $package->package_name }}</td>
                        <td>{{ $package->installed_version }}</td>
                        <td>{{ $package->advisory->title }} @if ($package->advisory->cve) ({{ $package->advisory->cve }}) @endif</td>
                        <td>{{ ucfirst($package->advisory->severity ?? 'unknown') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($resolved->isNotEmpty())
            <h2>Fixed in the last 30 days</h2>
            <table>
                <tbody>
                @foreach ($resolved as $finding)
                    <tr><td>{{ $finding->title }}</td><td class="muted">{{ $finding->resolved_at->toFormattedDateString() }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <footer>
            Generated by Surfacewatch. External checks only: no exploitation was attempted and no secret values were stored.
        </footer>
    </div>
</body>
</html>
