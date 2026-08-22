<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class DeveloperServicesController extends Controller
{
    /**
     * Check the status of Cloudflare and OCR services.
     */
    public function status(): JsonResponse
    {
        // Check Cloudflare by looking for cloudflared.exe in tasklist
        $cfRunning = false;
        exec('tasklist /FI "IMAGENAME eq cloudflared.exe" 2>NUL', $output);
        foreach ($output as $line) {
            if (stripos($line, 'cloudflared.exe') !== false) {
                $cfRunning = true;
                break;
            }
        }

        // Check OCR by hitting its health endpoint (port 8001)
        $ocrRunning = false;
        try {
            $response = Http::timeout(2)->get('http://127.0.0.1:8001/health');
            if ($response->successful()) {
                $ocrRunning = true;
            }
        } catch (\Exception $e) {
            // Not running
        }

        // Also return the currently configured activation URL so the frontend can display it
        preg_match('/^ACTIVATION_FRONTEND_URL=(.*)/m', file_get_contents(base_path('.env')), $matches);
        $activationUrl = rtrim((string) ($matches[1] ?? 'http://localhost:5173'), '/');

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
     * Runs cloudflared, waits up to 10 seconds to capture the assigned trycloudflare.com
     * URL from its output, then writes it to ACTIVATION_FRONTEND_URL in .env.
     */
    public function startCloudflare(): JsonResponse
    {
        $executable = realpath(base_path('../tools/cloudflared.exe'));

        if (! $executable || ! file_exists($executable)) {
            return response()->json([
                'message' => 'cloudflared.exe not found. Expected at: '.base_path('../tools/cloudflared.exe'),
            ], 404);
        }

        // Kill any existing cloudflared process first (clean restart)
        exec('taskkill /F /IM cloudflared.exe 2>NUL');
        sleep(1);

        $logFile = storage_path('logs/cloudflared.log');
        file_put_contents($logFile, ''); // clear old log

        // Tunnel the Vue frontend (port 5173) and redirect output to log file
        // start /B detaches the process completely so PHP artisan serve won't deadlock
        $cmd = 'cmd /c "start /B "" "'.escapeshellcmd($executable).'" tunnel --url http://localhost:5173 > "'.escapeshellcmd($logFile).'" 2>&1"';
        pclose(popen($cmd, 'r'));

        $tunnelUrl = null;
        $start = time();

        // Wait up to 15 seconds for cloudflared to output the URL to the log
        while (time() - $start < 15) {
            if (file_exists($logFile)) {
                $buffer = file_get_contents($logFile);
                // cloudflared outputs a line like: "https://xyz-abc.trycloudflare.com"
                if (preg_match('/(https?:\/\/[a-z0-9\-]+\.trycloudflare\.com)/i', $buffer, $m)) {
                    $tunnelUrl = $m[1];
                    break;
                }
            }
            usleep(300_000); // poll every 300ms
        }

        if (! $tunnelUrl) {
            return response()->json([
                'message' => 'Cloudflare tunnel started but could not capture the URL within 15 seconds. Check if it is running.',
                'data' => ['activation_base' => null],
            ], 202);
        }

        // Update ACTIVATION_FRONTEND_URL in the .env file
        $this->updateEnv('ACTIVATION_FRONTEND_URL', $tunnelUrl);

        return response()->json([
            'message' => 'Cloudflare tunnel started successfully.',
            'data' => [
                'tunnel_url' => $tunnelUrl,
                'activation_base' => $tunnelUrl,
            ],
        ]);
    }

    /**
     * Start the Python OCR Service (uvicorn) as a detached background process.
     */
    public function startOcr(): JsonResponse
    {
        $ocrDir = realpath(base_path('ocr-service'));
        $uvicorn = $ocrDir.DIRECTORY_SEPARATOR.'.venv'.DIRECTORY_SEPARATOR.'Scripts'.DIRECTORY_SEPARATOR.'uvicorn.exe';

        if (! $ocrDir || ! file_exists($uvicorn)) {
            return response()->json([
                'message' => 'OCR service venv/uvicorn not found at: '.$uvicorn,
            ], 404);
        }

        // Clean restart: kill any running uvicorn on port 8001
        exec('taskkill /F /IM uvicorn.exe 2>NUL');
        sleep(1);

        $cmd = 'cmd /c "cd /d "'.$ocrDir.'" && start /B "" "'.$uvicorn.'" app.main:app --host 127.0.0.1 --port 8001"';
        pclose(popen($cmd, 'r'));

        return response()->json([
            'message' => 'OCR service starting in background on port 8001. Refresh status in a few seconds.',
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

        // Also update the running config cache so artisan picks up the change
        putenv("{$key}={$value}");
    }
}
