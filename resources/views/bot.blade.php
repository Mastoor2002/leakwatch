<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SurfacewatchBot – how our scanner works</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; max-width: 720px; margin: 48px auto; padding: 0 20px; color: #18181b; line-height: 1.6; }
        h1 { font-size: 28px; } h2 { font-size: 18px; margin-top: 32px; }
        code { background: #f4f4f5; padding: 1px 6px; border-radius: 4px; }
        li { margin-bottom: 6px; }
    </style>
</head>
<body>
    <h1>SurfacewatchBot</h1>
    <p>If you saw <code>{{ config('leakwatch.http.user_agent') }}</code> in your logs, a Surfacewatch user asked us to check a website for common security misconfigurations.</p>

    <h2>What we do</h2>
    <ul>
        <li><strong>Passive checks on any added site:</strong> we load the homepage like a browser and read its headers and cookies, check the TLS certificate, and look up public DNS records (SPF, DMARC).</li>
        <li><strong>Path checks only after ownership is proven:</strong> requests to paths such as <code>/.env</code>, <code>/.git/HEAD</code> or <code>/telescope</code> are only made for sites whose owner added a DNS TXT record or a verification file.</li>
        <li>About 25 requests per scan, with a delay between them and a 10-second timeout.</li>
    </ul>

    <h2>What we never do</h2>
    <ul>
        <li>No exploitation, logins, brute force, payloads or crawling.</li>
        <li>No storage of secret values. If a file like <code>.env</code> is exposed, we record only that it is reachable and the names of the variables in it.</li>
        <li>No connections to private or internal network addresses.</li>
    </ul>

    <h2>Opting out</h2>
    <p>Path checks can't run without the site owner's verification, so removing the verification record or file stops them. You can also block the User-Agent above.</p>
</body>
</html>
