<?php

namespace App\Http\Controllers;

use App\Support\ActivationLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Local developer tooling: spawns/kills OS processes, reads and rewrites .env,
 * and opens a public Cloudflare tunnel to the app.
 *
 * These capabilities must never be reachable off a developer workstation, so
 * every action asserts the local environment first. The `role:developer,admin`
 * route guard is not sufficient on its own — an admin account on a deployed
 * instance would otherwise be able to expose the app publicly and read secrets.
 */
class DeveloperServicesController extends Controller
{
    /**
     * Abort unless running on a local developer machine.
     */
    private function assertLocalEnvironment(): void
    {
        abort_unless(app()->environment('local'), 404);
    }

    /**
     * Check the status of Cloudflare and OCR services.
     */
    public function status(): JsonResponse
    {
        $this->assertLocalEnvironment();

        // Check Cloudflare by looking for cloudflared.exe in tasklist
        $cfRunning = false;
        exec('tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL', $output);
        foreach ($output as $line) {
            if (stripos($line, 'cloudflared.exe') !== false) {
                $cfRunning = true;
                break;
            }
        }

        // If cloudflared is dead, clear the runtime cached tunnel
        if (! $cfRunning) {
            Cache::forget('runtime_activation_frontend_url');
        }

        // Check OCR by hitting its health endpoint (port 8081)
        $ocrRunning = false;
        try {
            $response = Http::timeout(2)->get('http://127.0.0.1:8081/health');
            if ($response->successful()) {
                $ocrRunning = true;
            }
        } catch (\Exception $e) {
            // Not running
        }

        // Also return the currently configured activation URL so the frontend can display it.
        $activationUrl = ActivationLink::base();

        return response()->json([
            'data' => [
                'cloudflare' => $cfRunning,
                'ocr' => $ocrRunning,
                'activation_base' => $activationUrl,
            ],
        ]);
    }

    /**
     * Start the Cloudflare Tunnel.
     *
     * Writes a tiny PowerShell wrapper script, then fires it with
     * `Start-Process powershell -File wrapper.ps1 -WindowStyle Hidden`.
     * Because Start-Process spawns a brand-new process tree it does NOT inherit
     * PHP's open TCP sockets, which prevents port-8010 handle-lock on Windows.
     * Output (stdout+stderr) is captured inside the wrapper, not through
     * -RedirectStandardOutput/-RedirectStandardError (those flags make
     * Start-Process synchronous/blocking and cannot share the same file).
     */
    public function startCloudflare(): JsonResponse
    {
        $this->assertLocalEnvironment();

        $executable = realpath(base_path('../tools/cloudflared.exe'));

        if (! $executable || ! file_exists($executable)) {
            return response()->json([
                'message' => 'cloudflared.exe not found. Expected at: '.base_path('../tools/cloudflared.exe'),
            ], 404);
        }

        // Kill any existing cloudflared process first (clean restart)
        exec('taskkill /F /IM cloudflared.exe 2>NUL');
        sleep(1);

        $logFile   = storage_path('logs/cloudflared.log');
        $scriptPath = storage_path('logs/start-cloudflared.ps1');

        // Clear old log
        file_put_contents($logFile, '');

        // Write a small PS1 script that runs cloudflared and merges stdout+stderr
        // into the log file via a pipeline — no -Redirect* flags on Start-Process,
        // so the outer Start-Process call is non-blocking.
        $exe = str_replace("'", "''", $executable);
        $log = str_replace("'", "''", $logFile);
        $psScript = "& '{$exe}' tunnel --url http://localhost:5173 2>&1 | Out-File -FilePath '{$log}' -Encoding utf8 -Append\r\n";
        file_put_contents($scriptPath, $psScript);

        // Launch the wrapper in a fully detached PowerShell process.
        // -WindowStyle Hidden + -File wrapper.ps1 runs without a visible window,
        // and Start-Process creates a new process tree with no inherited handles.
        $script = str_replace("'", "''", $scriptPath);
        $launchCmd = "powershell -NoProfile -NonInteractive -Command \"Start-Process powershell -ArgumentList '-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-File','{$script}' -WindowStyle Hidden\"";
        exec($launchCmd);

        $tunnelUrl = null;
        $start = time();

        // Poll for up to 20 seconds for cloudflared to write the URL to the log
        while (time() - $start < 20) {
            if (file_exists($logFile)) {
                $buffer = file_get_contents($logFile);
                if (preg_match('/(https?:\/\/[a-z0-9\-]+\.trycloudflare\.com)/i', $buffer, $m)) {
                    $tunnelUrl = $m[1];
                    break;
                }
            }
            usleep(400_000); // poll every 400ms
        }

        if (! $tunnelUrl) {
            return response()->json([
                'message' => 'Cloudflare tunnel started but could not capture the URL within 15 seconds. Check if it is running.',
                'data' => ['activation_base' => ActivationLink::base()],
            ], 202);
        }

        // Store runtime tunnel in Cache and active config — NO disk .env modification
        // to prevent triggering artisan serve reload loops and deadlocks.
        Cache::forever('runtime_activation_frontend_url', $tunnelUrl);
        config(['app.activation_frontend_url' => $tunnelUrl]);

        return response()->json([
            'message' => 'Cloudflare tunnel started successfully.',
            'data' => [
                'tunnel_url' => $tunnelUrl,
                'activation_base' => $tunnelUrl,
            ],
        ]);
    }

    /**
     * Stop the Cloudflare Tunnel.
     */
    public function stopCloudflare(): JsonResponse
    {
        $this->assertLocalEnvironment();

        exec('taskkill /F /IM cloudflared.exe 2>NUL');
        Cache::forget('runtime_activation_frontend_url');
        config(['app.activation_frontend_url' => null]);

        return response()->json([
            'message' => 'Cloudflare tunnel stopped.',
            'data' => [
                'activation_base' => ActivationLink::base(),
            ],
        ]);
    }

    /**
     * Start the Python OCR Service (uvicorn) as a detached background process.
     */
    public function startOcr(): JsonResponse
    {
        $this->assertLocalEnvironment();

        $ocrDir = realpath(base_path('ocr-service'));
        $uvicorn = $ocrDir.DIRECTORY_SEPARATOR.'.venv'.DIRECTORY_SEPARATOR.'Scripts'.DIRECTORY_SEPARATOR.'uvicorn.exe';

        if (! $ocrDir || ! file_exists($uvicorn)) {
            return response()->json([
                'message' => 'OCR service venv/uvicorn not found at: '.$uvicorn,
            ], 404);
        }

        // Clean restart: kill whatever process is currently on port 8081
        exec('powershell -NoProfile -Command "(Get-NetTCPConnection -LocalPort 8081 -ErrorAction SilentlyContinue).OwningProcess | ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }" 2>NUL');
        exec('taskkill /F /IM uvicorn.exe 2>NUL');
        sleep(1);

        $cmd = sprintf(
            'powershell -NoProfile -NonInteractive -Command "Start-Process -FilePath \'%s\' -ArgumentList \'app.main:app --host 127.0.0.1 --port 8081\' -WorkingDirectory \'%s\' -WindowStyle Hidden"',
            addslashes($uvicorn),
            addslashes($ocrDir)
        );
        exec($cmd);

        return response()->json([
            'message' => 'OCR service starting in background on port 8081. Refresh status in a few seconds.',
        ]);
    }

    /**
     * Stop the Python OCR Service.
     */
    public function stopOcr(): JsonResponse
    {
        $this->assertLocalEnvironment();

        exec('powershell -NoProfile -Command "(Get-NetTCPConnection -LocalPort 8081 -ErrorAction SilentlyContinue).OwningProcess | ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }" 2>NUL');
        exec('taskkill /F /IM uvicorn.exe 2>NUL');

        return response()->json([
            'message' => 'OCR service stopped.',
        ]);
    }

    /**
     * Write or update a key=value pair in the .env file.
     */
    private function updateEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $content = file_get_contents($envPath);

        $escaped = preg_quote($key, '/');

        if (preg_match("/^{$escaped}=.*/m", $content)) {
            // Replace existing line
            $content = preg_replace("/^{$escaped}=.*/m", "{$key}={$value}", $content);
        } else {
            // Append new line at end
            $content .= "\n{$key}={$value}\n";
        }

        file_put_contents($envPath, $content);

        // Also update the running process so the change applies without a restart.
        // config() must be set explicitly: activation links now resolve through
        // config('app.activation_frontend_url'), which was bound at boot and does
        // not observe putenv().
        putenv("{$key}={$value}");
        if ($key === 'ACTIVATION_FRONTEND_URL') {
            config(['app.activation_frontend_url' => $value]);
        }
    }
}
