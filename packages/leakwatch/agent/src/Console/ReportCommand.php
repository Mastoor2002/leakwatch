<?php

namespace Leakwatch\Agent\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Leakwatch\Agent\LockfileReader;
use Throwable;

class ReportCommand extends Command
{
    protected $signature = 'leakwatch:report {--dry-run : Show what would be sent without sending it}';

    protected $description = 'Send installed Composer package versions to Leakwatch';

    public function handle(LockfileReader $reader): int
    {
        $endpoint = (string) config('leakwatch-agent.endpoint');
        $token = (string) config('leakwatch-agent.token');

        try {
            $lock = $reader->read(base_path('composer.lock'), (bool) config('leakwatch-agent.include_dev'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $payload = [
            'lock_hash' => $lock['lock_hash'],
            'php_version' => PHP_VERSION,
            'laravel_version' => Application::VERSION,
            'packages' => $lock['packages'],
        ];

        if ($this->option('dry-run')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($endpoint === '' || $token === '') {
            $this->error('Set LEAKWATCH_ENDPOINT and LEAKWATCH_TOKEN in .env first.');

            return self::FAILURE;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post($endpoint, $payload);

        if ($response->failed()) {
            $this->error("Leakwatch rejected the report ({$response->status()}): ".$response->json('message', $response->body()));

            return self::FAILURE;
        }

        $this->info('Reported '.count($payload['packages']).' packages to Leakwatch.');

        return self::SUCCESS;
    }
}
