<?php

namespace App\Services\Forms;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FormsClient
{
    public function __construct(
        private readonly string $apiBase = '',
        private readonly ?string $apiKey = null,
        private readonly ?float $timeout = null,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createSubmission(array $payload): array
    {
        return $this->requestJson('post', '/api/forms/submissions', $payload);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function listSubmissions(array $query): array
    {
        return $this->requestJson('get', '/api/forms/submissions', $query);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function getSubmission(string $publicId, array $query): array
    {
        return $this->requestJson('get', '/api/forms/submissions/' . rawurlencode($publicId), $query);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function markRead(string $publicId, array $payload): array
    {
        return $this->requestJson('patch', '/api/forms/submissions/' . rawurlencode($publicId) . '/read', $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function markAllRead(array $payload): array
    {
        return $this->requestJson('patch', '/api/forms/submissions/read', $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function deleteSubmission(string $publicId, array $payload): array
    {
        return $this->requestJson('delete', '/api/forms/submissions/' . rawurlencode($publicId), $payload);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function exportSubmissions(array $query): array
    {
        return $this->requestJson('get', '/api/forms/submissions/export', $query);
    }

    /**
     * @return array<string,mixed>
     */
    public function pruneExpired(): array
    {
        $maintenanceSecret = trim((string) config('forms.maintenance_secret', ''));
        if ($maintenanceSecret === '') {
            $maintenanceSecret = trim((string) config('forms.api_key', ''));
        }

        $headers = [
            'X-Maintenance-Source' => 'forms:prune',
        ];
        if ($maintenanceSecret !== '') {
            $headers['X-Maintenance-Secret'] = $maintenanceSecret;
        }

        return $this->requestJson('post', '/api/forms/maintenance/prune', [], $headers);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function requestJson(string $method, string $path, array $data = [], array $extraHeaders = []): array
    {
        $base = $this->apiBase ?: (string) config('forms.api_base');
        if ($base === '') {
            return ['ok' => false, 'error' => 'forms_api_base_missing'];
        }

        try {
            $request = Http::withHeaders(array_merge($this->buildHeaders(), $extraHeaders))
                ->timeout($this->timeout())
                ->acceptJson();

            $url = rtrim($base, '/') . $path;
            $response = $method === 'get'
                ? $request->get($url, $data)
                : $request->{$method}($url, $data);

            if ($response->failed()) {
                $this->logFailure($response, $path);
                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'error' => $response->json('error.code') ?: $response->json('error') ?: 'forms_api_failed',
                ];
            }

            $json = $response->json();
            return is_array($json) ? array_merge(['ok' => true], $json) : ['ok' => true];
        } catch (Throwable $e) {
            Log::warning('Forms API request failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'forms_api_unreachable'];
        }
    }

    /**
     * @return array<string,string>
     */
    private function buildHeaders(): array
    {
        $headers = [
            'User-Agent' => 'Wayvio-Forms-Bridge',
        ];

        $key = $this->apiKey ?: (string) config('forms.api_key');
        if ($key !== '') {
            $headers['X-API-KEY'] = $key;
        }

        return $headers;
    }

    private function timeout(): float
    {
        return $this->timeout ?? (float) config('forms.http_timeout', 5.0);
    }

    private function logFailure(Response $response, string $path): void
    {
        Log::warning('Forms API returned non-success response', [
            'path' => $path,
            'status' => $response->status(),
            'error' => $response->json('error.code') ?: $response->json('error'),
        ]);
    }
}
