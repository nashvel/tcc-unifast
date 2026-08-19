<?php

namespace Database\Seeders;

use App\Models\ActivationToken;
use App\Models\Batch;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\KycProfile;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds three real-name grantees with fresh activation tokens for mobile E2E.
 *
 * Run:
 *   C:\php84\php.exe artisan db:seed --class=MobileActivationSeeder
 *
 * Flow on phone: open URL → set new password (token-only) → KYC → ID scan → liveness.
 *
 * Frontend base resolution (mobile-first):
 *   1. ACTIVATION_FRONTEND_URL (explicit override)
 *   2. FRONTEND_URL first origin, if HTTP-reachable
 *   3. Detected LAN IP + live Vite port (phone on same Wi‑Fi)
 *   4. localhost + Vite port (desktop only)
 */
class MobileActivationSeeder extends Seeder
{
    public const TEMP_PASSWORD = 'TCC-TEST-ACT1';

    /**
     * @var list<array{student_id: string, student_number: string, full_name: string, email: string, program: string}>
     */
    private const GRANTEES = [
        [
            'student_id' => '20231011',
            'student_number' => '20231011',
            'full_name' => 'Rhio Bacalso',
            'email' => 'rhio.bacalso@tcc.edu.ph',
            'program' => 'BSIT',
        ],
        [
            'student_id' => '20231909',
            'student_number' => '20231909',
            'full_name' => 'Rafael Balacuit',
            'email' => 'rafael.balacuit@tcc.edu.ph',
            'program' => 'BSIT',
        ],
        [
            'student_id' => '20231913',
            'student_number' => '20231913',
            'full_name' => 'Richard Cainoy',
            'email' => 'richard.cainoy@tcc.edu.ph',
            'program' => 'BSIT',
        ],
    ];

    public function run(): void
    {
        $batch = Batch::query()->updateOrCreate(
            ['name' => 'TES AY 2026-2027 1st (Activation E2E)'],
            [
                'academic_year' => '2026-2027',
                'semester' => '1st Semester',
                'status' => 'active',
                'window_status' => 'active',
                'is_active' => true,
                'submission_deadline' => now()->addDays(45),
            ],
        );

        $resolved = $this->resolveFrontendBases();
        $frontend = $resolved['mobile'];
        $lines = [
            '',
            '=== Mobile activation links ===',
            'Batch: '.$batch->name.' (id '.$batch->id.')',
            'Resolved via: '.$resolved['source'],
            'Mobile / primary base: '.$frontend,
            'Desktop localhost base: '.$resolved['localhost'],
            'Temporary password (all three): '.self::TEMP_PASSWORD,
            '',
            'NOTE: Phone must be on the same Wi‑Fi as this PC when using a LAN IP.',
            'NOTE: Cloudflare trycloudflare.com links only work while that tunnel process is alive.',
            '',
        ];

        $adminId = User::query()->where('role', 'admin')->value('id')
            ?? User::query()->where('role', 'developer')->value('id');

        $import = MasterlistImport::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'original_name' => 'mobile-activation-seed.csv',
            ],
            [
                'uploaded_by' => $adminId,
                'stored_path' => 'masterlist-imports/mobile-activation-seed.csv',
                'status' => 'imported',
                'total_rows' => count(self::GRANTEES),
                'valid_rows' => count(self::GRANTEES),
                'imported_rows' => count(self::GRANTEES),
            ],
        );

        foreach (self::GRANTEES as $index => $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['full_name'],
                    'role' => 'student',
                    'student_id' => $row['student_id'],
                    'account_status' => 'unverified',
                    'password' => Hash::make(self::TEMP_PASSWORD),
                    'email_verified_at' => null,
                    'activated_at' => null,
                ],
            );

            // Invalidate prior unused tokens so this seed always yields fresh links.
            ActivationToken::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->delete();

            $grantee = Grantee::query()->updateOrCreate(
                ['student_id' => $row['student_id'], 'batch_id' => $batch->id],
                [
                    'user_id' => $user->id,
                    'student_number' => $row['student_number'],
                    'full_name' => $row['full_name'],
                    'email' => $row['email'],
                    'program' => $row['program'],
                    'year_level' => '1',
                    'status' => 'unverified',
                    'submission_status' => 'not_submitted',
                ],
            );

            // Fresh activation E2E: clear prior KYC / ID / liveness so OCR re-scan is required.
            KycProfile::query()->where('user_id', $user->id)->delete();
            GranteeIdentityProfile::query()->where('grantee_id', $grantee->id)->delete();

            MasterlistRow::query()->updateOrCreate(
                [
                    'masterlist_import_id' => $import->id,
                    'student_id' => $row['student_id'],
                ],
                [
                    'row_number' => $index + 1,
                    'student_number' => $row['student_number'],
                    'full_name' => $row['full_name'],
                    'email' => $row['email'],
                    'program' => $row['program'],
                    'year_level' => '1',
                    'status' => 'valid',
                ],
            );

            $plainToken = Str::random(48);
            ActivationToken::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addDays(14),
            ]);

            $path = '/activate/'.$plainToken.'?lang=en';
            $lines[] = sprintf(
                '%s | student_id=%s | email=%s | grantee_id=%d',
                $row['full_name'],
                $row['student_id'],
                $row['email'],
                $grantee->id,
            );
            $lines[] = '  Temp password: '.self::TEMP_PASSWORD;
            $lines[] = '  TOKEN: '.$plainToken;
            $lines[] = '  URL (phone / primary): '.$frontend.$path;
            if ($resolved['localhost'] !== $frontend) {
                $lines[] = '  URL (desktop localhost): '.$resolved['localhost'].$path;
            }
            if ($resolved['lan'] !== null && $resolved['lan'] !== $frontend) {
                $lines[] = '  URL (LAN): '.$resolved['lan'].$path;
            }
            $lines[] = '';
        }

        $lines[] = 'Mobile flow: open URL on phone → set new password (no temp password) → KYC → ID scan → liveness → vault.';
        $lines[] = 'Vite must listen on 0.0.0.0 (already in vite.config). If needed: npm run dev -- --host 0.0.0.0';
        $lines[] = '';

        foreach ($lines as $line) {
            $this->command?->info($line);
        }
    }

    /**
     * @return array{mobile: string, localhost: string, lan: ?string, source: string}
     */
    protected function resolveFrontendBases(): array
    {
        $vitePort = $this->detectVitePort();
        $lanIp = $this->detectLanIpv4();
        $lan = $lanIp !== null ? 'http://'.$lanIp.':'.$vitePort : null;
        $localhost = 'http://localhost:'.$vitePort;

        $override = $this->firstOrigin((string) env('ACTIVATION_FRONTEND_URL', ''));
        if ($override !== null) {
            return [
                'mobile' => $override,
                'localhost' => $localhost,
                'lan' => $lan,
                'source' => 'ACTIVATION_FRONTEND_URL',
            ];
        }

        $configured = $this->firstOrigin((string) env('FRONTEND_URL', ''));
        if ($configured !== null && $this->urlLooksReachable($configured)) {
            return [
                'mobile' => $configured,
                'localhost' => $localhost,
                'lan' => $lan,
                'source' => 'FRONTEND_URL (reachable)',
            ];
        }

        if ($configured !== null) {
            $this->command?->warn(
                'FRONTEND_URL is set but not reachable ('.$configured.'); falling back to LAN/localhost.',
            );
        }

        if ($lan !== null && $this->urlLooksReachable($lan)) {
            return [
                'mobile' => $lan,
                'localhost' => $localhost,
                'lan' => $lan,
                'source' => 'detected LAN + Vite :'.$vitePort,
            ];
        }

        if ($lan !== null) {
            return [
                'mobile' => $lan,
                'localhost' => $localhost,
                'lan' => $lan,
                'source' => 'detected LAN (Vite probe failed; start vite --host)',
            ];
        }

        return [
            'mobile' => $localhost,
            'localhost' => $localhost,
            'lan' => null,
            'source' => 'localhost fallback',
        ];
    }

    private function firstOrigin(string $raw): ?string
    {
        foreach (explode(',', $raw) as $part) {
            $origin = rtrim(trim($part), '/');
            if ($origin !== '') {
                return $origin;
            }
        }

        return null;
    }

    private function detectVitePort(): int
    {
        foreach ([5174, 5173] as $port) {
            if ($this->tcpPortOpen('127.0.0.1', $port)) {
                return $port;
            }
        }

        return 5173;
    }

    private function detectLanIpv4(): ?string
    {
        // Prefer Windows ipconfig (reliable for Wi‑Fi / Ethernet adapters).
        if (PHP_OS_FAMILY === 'Windows') {
            $out = [];
            @exec('ipconfig', $out);
            $currentAdapter = null;
            $candidates = [];
            foreach ($out as $line) {
                if (preg_match('/adapter\s+(.+):/i', $line, $m) === 1) {
                    $currentAdapter = trim($m[1]);

                    continue;
                }
                if (preg_match('/IPv4 Address[^:]*:\s*([0-9.]+)/i', $line, $m) !== 1) {
                    continue;
                }
                $ip = $m[1];
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                    && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    && ! str_starts_with($ip, '127.')
                    && ! str_starts_with($ip, '169.254.')) {
                    $score = 0;
                    $name = strtolower((string) $currentAdapter);
                    if (str_contains($name, 'wi-fi') || str_contains($name, 'wifi') || str_contains($name, 'wireless')) {
                        $score += 20;
                    }
                    if (str_contains($name, 'ethernet')) {
                        $score += 10;
                    }
                    if (str_starts_with($ip, '192.168.')) {
                        $score += 5;
                    }
                    $candidates[] = ['ip' => $ip, 'score' => $score];
                }
            }
            if ($candidates !== []) {
                usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

                return $candidates[0]['ip'];
            }
        }

        $hostnameIp = gethostbyname(gethostname() ?: 'localhost');
        if (is_string($hostnameIp)
            && filter_var($hostnameIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && ! str_starts_with($hostnameIp, '127.')) {
            return $hostnameIp;
        }

        return null;
    }

    private function tcpPortOpen(string $host, int $port): bool
    {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 0.4);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    private function urlLooksReachable(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $host = (string) $parts['host'];
        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);

        // Dead / expired quick tunnels often still appear in .env; require DNS + TCP.
        if (str_ends_with($host, '.trycloudflare.com')) {
            $resolved = @gethostbyname($host);
            if ($resolved === $host || $resolved === '' || $resolved === false) {
                return false;
            }
        }

        if (! $this->tcpPortOpen($host, $port) && $scheme === 'http') {
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2.5,
                'ignore_errors' => true,
                'header' => "User-Agent: MobileActivationSeeder\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        return $body !== false;
    }
}
