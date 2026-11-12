<?php

namespace App\Scanning\Net;

use App\Scanning\Contracts\TlsInspector;
use App\Scanning\Data\TlsCertificate;
use Carbon\CarbonImmutable;

/**
 * Reads a server's TLS certificate with PHP streams. We connect to the IP that
 * SafeHttp already approved (no second DNS lookup) and send the host name via SNI.
 */
class StreamTlsInspector implements TlsInspector
{
    public function inspect(string $host, string $ip, int $port = 443): ?TlsCertificate
    {
        $target = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? "[{$ip}]" : $ip;

        [$client, $error] = $this->connect($target, $port, $host, verify: true);
        $trusted = $client !== false;

        if ($client === false) {
            [$client] = $this->connect($target, $port, $host, verify: false);

            if ($client === false) {
                return null;
            }
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($certificate === null) {
            return null;
        }

        $info = openssl_x509_parse($certificate);

        if ($info === false) {
            return null;
        }

        $altNames = [];

        foreach (explode(',', (string) ($info['extensions']['subjectAltName'] ?? '')) as $entry) {
            $entry = trim($entry);

            if (str_starts_with($entry, 'DNS:')) {
                $altNames[] = substr($entry, 4);
            }
        }

        return new TlsCertificate(
            subject: (string) ($info['subject']['CN'] ?? ''),
            issuer: (string) ($info['issuer']['O'] ?? $info['issuer']['CN'] ?? ''),
            validFrom: CarbonImmutable::createFromTimestamp((int) $info['validFrom_time_t']),
            validTo: CarbonImmutable::createFromTimestamp((int) $info['validTo_time_t']),
            altNames: $altNames,
            trusted: $trusted,
            error: $trusted ? null : ($error ?: 'Certificate could not be verified.'),
        );
    }

    /**
     * @return array{0: resource|false, 1: string}
     */
    private function connect(string $target, int $port, string $host, bool $verify): array
    {
        $options = [
            'capture_peer_cert' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => ! $verify,
        ];

        $caFile = ini_get('openssl.cafile') ?: ini_get('curl.cainfo');

        if ($verify && $caFile) {
            $options['cafile'] = $caFile;
        }

        $context = stream_context_create(['ssl' => $options]);

        $client = @stream_socket_client(
            "ssl://{$target}:{$port}",
            $errorNumber,
            $errorMessage,
            8,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        return [$client, (string) $errorMessage];
    }
}
