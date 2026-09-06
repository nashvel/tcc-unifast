<?php

namespace App\Services\Continuity;

use App\Models\GoogleWorkspaceConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleWorkspaceClient
{
    public const SCOPES = ['https://www.googleapis.com/auth/drive', 'https://www.googleapis.com/auth/spreadsheets', 'https://www.googleapis.com/auth/forms.body.readonly', 'https://www.googleapis.com/auth/forms.responses.readonly', 'https://www.googleapis.com/auth/userinfo.email', 'https://www.googleapis.com/auth/userinfo.profile'];

    public function configured(): bool
    {
        return (bool) config('continuity.google.client_id') && (bool) config('continuity.google.client_secret');
    }

    public function exchange(array $parameters): array
    {
        return $this->request('POST', 'https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id' => config('continuity.google.client_id'),
                'client_secret' => config('continuity.google.client_secret'),
                ...$parameters,
            ],
        ]);
    }

    public function token(): string
    {
        return Cache::lock('continuity:google-token', 60)->block(5, function (): string {
            $connection = GoogleWorkspaceConnection::first();
            abort_unless($connection && $connection->status === 'connected', 409, 'Connect Google Workspace first.');
            if ($connection->access_token && $connection->expires_at?->isAfter(now()->addMinute())) {
                return $connection->access_token;
            }
            abort_unless($connection->refresh_token, 409, 'Reconnect Google Workspace.');
            try {
                $token = $this->exchange(['grant_type' => 'refresh_token', 'refresh_token' => $connection->refresh_token]);
            } catch (GoogleAuthorizationExpired $exception) {
                $connection->update(['status' => 'reconnect_required', 'enabled' => false]);
                throw $exception;
            }
            if (! is_string($token['access_token'] ?? null) || $token['access_token'] === '' || ! is_numeric($token['expires_in'] ?? null) || (int) $token['expires_in'] <= 0) {
                throw ValidationException::withMessages(['google' => 'Google returned an incomplete token response.']);
            }
            $attributes = ['access_token' => $token['access_token'], 'expires_at' => now()->addSeconds((int) $token['expires_in'])];
            if (is_string($token['refresh_token'] ?? null) && $token['refresh_token'] !== '') {
                $attributes['refresh_token'] = $token['refresh_token'];
            }
            $connection->update($attributes);

            return $connection->access_token;
        });
    }

    public function api(string $method, string $service, string $path, array $options = []): array
    {
        $base = match ($service) {
            'drive' => 'https://www.googleapis.com/drive/v3/',
            'sheets' => 'https://sheets.googleapis.com/v4/spreadsheets/',
            'forms' => 'https://forms.googleapis.com/v1/forms/',
            default => throw new \InvalidArgumentException('Unknown Google service.'),
        };

        return $this->request($method, $base.$path, $options, $this->token());
    }

    public function profile(string $token): array
    {
        return $this->request('GET', 'https://www.googleapis.com/oauth2/v2/userinfo', [], $token);
    }

    private function request(string $method, string $url, array $options, ?string $token = null): array
    {
        $request = Http::acceptJson()->timeout(max(1, (int) config('continuity.google.http_timeout', 20)))->connectTimeout(5)->withoutRedirecting();
        if ($token !== null) {
            $request = $request->withToken($token);
        }
        try {
            $response = $request->send($method, $url, $options);
        } catch (ConnectionException) {
            abort(503, 'Google Workspace is temporarily unavailable.');
        }
        if ($response->status() === 204) {
            return [];
        }
        if ($response->serverError() || $response->status() === 429) {
            abort(503, 'Google Workspace is temporarily unavailable. Try again later.');
        }
        if ($url === 'https://oauth2.googleapis.com/token' && $response->status() === 400 && $response->json('error') === 'invalid_grant') {
            throw GoogleAuthorizationExpired::withMessages(['google' => 'Google authorization expired or was revoked. Reconnect Google Workspace.']);
        }
        if (! $response->successful() || ! is_array($response->json())) {
            // Never reflect Google's raw response or credential-bearing request.
            throw ValidationException::withMessages(['google' => 'Google Workspace could not complete this operation. Check connection access and try again.']);
        }

        return $response->json();
    }
}
