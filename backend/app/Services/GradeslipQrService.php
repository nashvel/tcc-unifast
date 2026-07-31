<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class GradeslipQrService
{
    /**
     * Decode the TCC Grade Slip "Scan to Verify" QR via python/gradeslip_qr.py.
     *
     * @return array{
     *     status: string,
     *     success: bool,
     *     found: bool,
     *     domain_valid: bool,
     *     raw_payload: ?string,
     *     parsed_fields: array<string, mixed>,
     *     error: ?string,
     *     error_code: ?string,
     *     engine?: string,
     *     codes?: array<int, mixed>
     * }
     */
    public function decode(string $absolutePath): array
    {
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            return $this->failure('file_missing', 'Grade slip file missing for QR decode.');
        }

        $python = $this->resolvePythonBinary();
        $script = base_path('python/gradeslip_qr.py');
        if (! is_file($script)) {
            return $this->failure('script_missing', 'gradeslip_qr.py not found under python/.');
        }

        $domains = implode(',', config('services.identity.tcc_registrar_domains', []));
        $timeout = (int) config('services.gradeslip_qr.timeout', 60);

        try {
            $result = Process::timeout($timeout)
                ->env([
                    'TCC_REGISTRAR_DOMAINS' => $domains,
                ])
                ->run([
                    $python,
                    $script,
                    $absolutePath,
                ]);
        } catch (\Throwable $exception) {
            Log::warning('gradeslip_qr.process_failed', ['error' => $exception->getMessage()]);

            return $this->failure('process_failed', $exception->getMessage());
        }

        $stdout = trim($result->output());
        $payload = $this->parseJson($stdout);
        if ($payload === null) {
            $stderr = trim($result->errorOutput());
            Log::warning('gradeslip_qr.invalid_json', [
                'exit' => $result->exitCode(),
                'stdout' => Str::limit($stdout, 500),
                'stderr' => Str::limit($stderr, 500),
            ]);

            return $this->failure(
                'invalid_json',
                $stderr !== '' ? $stderr : 'gradeslip_qr.py returned non-JSON output.',
            );
        }

        $errorCode = data_get($payload, 'error_code');
        if ($errorCode === 'dependency_missing') {
            return array_merge($this->normalize($payload), [
                'status' => 'unavailable',
            ]);
        }

        $success = (bool) data_get($payload, 'success', false);
        $found = (bool) data_get($payload, 'found', false);
        $domainValid = (bool) data_get($payload, 'domain_valid', false);

        return array_merge($this->normalize($payload), [
            'status' => $success ? 'ok' : 'invalid',
            'success' => $success,
            'found' => $found,
            'domain_valid' => $domainValid,
        ]);
    }

    private function resolvePythonBinary(): string
    {
        $configured = trim((string) config('services.gradeslip_qr.python'));
        if ($configured !== '') {
            return $configured;
        }

        $candidates = [
            base_path('python/.venv/Scripts/python.exe'),
            base_path('python/.venv/bin/python'),
            'python',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === 'python' || is_file($candidate)) {
                return $candidate;
            }
        }

        return 'python';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJson(string $stdout): ?array
    {
        if ($stdout === '') {
            return null;
        }

        $decoded = json_decode($stdout, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        return [
            'status' => (string) data_get($payload, 'status', 'invalid'),
            'success' => (bool) data_get($payload, 'success', false),
            'found' => (bool) data_get($payload, 'found', false),
            'domain_valid' => (bool) data_get($payload, 'domain_valid', false),
            'raw_payload' => data_get($payload, 'raw_payload'),
            'parsed_fields' => (array) data_get($payload, 'parsed_fields', []),
            'error' => data_get($payload, 'error'),
            'error_code' => data_get($payload, 'error_code'),
            'engine' => (string) data_get($payload, 'engine', 'pyzbar'),
            'codes' => (array) data_get($payload, 'codes', []),
            'decode_source' => data_get($payload, 'decode_source'),
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     success: bool,
     *     found: bool,
     *     domain_valid: bool,
     *     raw_payload: null,
     *     parsed_fields: array<never, never>,
     *     error: string,
     *     error_code: string
     * }
     */
    private function failure(string $code, string $message): array
    {
        return [
            'status' => $code === 'dependency_missing' ? 'unavailable' : $code,
            'success' => false,
            'found' => false,
            'domain_valid' => false,
            'raw_payload' => null,
            'parsed_fields' => [],
            'error' => $message,
            'error_code' => $code,
        ];
    }
}
