<?php

namespace App\Services\Storage;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * HTTP-Client für den R2-Backup-Worker.
 *
 * Alle Requests werden mit Bearer-Token und X-Worker-Secret authentifiziert.
 * Uploads streamen direkt zum Worker (kein vollständiges In-Memory-Laden).
 *
 * Die sessionId wird einmalig pro Client-Instanz erzeugt. Da Laravel den Disk
 * innerhalb eines Prozesses cached, teilen alle Uploads eines Artisan-Kommandos
 * dieselbe Session → zählen als eine Backup-Operation im Worker Rate-Limit.
 */
class WorkerR2Client
{
    private readonly string $sessionId;

    public function __construct(
        private readonly string $workerUrl,
        private readonly string $authToken,
        private readonly string $sharedSecret,
        private readonly int $timeout = 120,
    ) {
        $this->sessionId = (string) Str::uuid();
    }

    /**
     * Lädt eine Datei zum Worker hoch (streamed).
     *
     * @param resource|string $stream
     */
    public function upload(string $key, mixed $stream): void
    {
        $body = Utils::streamFor($stream);

        $response = $this->request()
            ->withHeaders(['X-Backup-Session' => $this->sessionId])
            ->withBody($body, 'application/octet-stream')
            ->put($this->url('/v1/backup/object', ['key' => $key]));

        $this->assertSuccess($response, "upload [{$key}]");
    }

    public function download(string $key): mixed
    {
        if (!preg_match('~(^|/)manifest\.json$~i', $key)) {
            throw new RuntimeException(
                "Worker download ist für Backup-Artefakte deaktiviert: {$key}. Nur manifest.json darf für Status-/UI-Zwecke gelesen werden; Restore-Artefakte müssen manuell aus Cloudflare R2 heruntergeladen und per SCP bereitgestellt werden."
            );
        }

        $guzzle = new \GuzzleHttp\Client([
            'timeout' => $this->timeout,
            'headers' => $this->authHeaders(),
        ]);

        $response = $guzzle->get(
            $this->url('/v1/backup/object', ['key' => $key]),
            ['stream' => true],
        );

        if ($response->getStatusCode() === 404) {
            throw new RuntimeException("Manifest nicht gefunden: {$key}");
        }

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException("Worker manifest download fehlgeschlagen [{$response->getStatusCode()}]: {$key}");
        }

        $resource = $response->getBody()->detach();

        if (!is_resource($resource)) {
            throw new RuntimeException("Worker lieferte keinen lesbaren Manifest-Stream für: {$key}");
        }

        return $resource;
    }

    public function delete(string $key): void
    {
        throw new RuntimeException(
            "Worker delete ist deaktiviert: {$key}. Löschungen müssen über einen separaten manuellen Cloudflare-Prozess erfolgen."
        );
    }

    /**
     * Listet Objekte im Bucket, optional gefiltert nach Prefix.
     *
     * @return array<int, array{key: string, size_bytes: int, last_modified: string|null, etag: string|null}>
     */
    public function list(string $prefix = ''): array
    {
        $params = $prefix !== '' ? ['prefix' => $prefix] : [];
        $response = $this->request()->get($this->url('/v1/backup/list', $params));

        $this->assertSuccess($response, 'list');

        return $response->json('result.objects', []);
    }

    /**
     * Sucht ein einzelnes Objekt anhand seines exakten Keys.
     *
     * Nutzt prefix-gefiltertes Listing für minimalen Datentransfer.
     *
     * @return array{key: string, size_bytes: int, last_modified: string|null}|null
     */
    public function findObject(string $key): ?array
    {
        $objects = $this->list($key);

        foreach ($objects as $obj) {
            if (($obj['key'] ?? '') === $key) {
                return $obj;
            }
        }

        return null;
    }

    /**
     * Gibt aktuelle Quota-Informationen zurück.
     *
     * @return array{used_bytes: int, used_gb: float, limit_bytes: int, limit_gb: float, blocked: bool}
     */
    public function quota(): array
    {
        $response = $this->request()->get($this->url('/v1/backup/quota'));
        $this->assertSuccess($response, 'quota');

        return $response->json('result.quota', []);
    }

    private function request(): PendingRequest
    {
        return Http::timeout($this->timeout)->withHeaders($this->authHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->authToken}",
            'X-Worker-Secret' => $this->sharedSecret,
        ];
    }

    /**
     * @param array<string, string|int> $params
     */
    private function url(string $path, array $params = []): string
    {
        $base = rtrim($this->workerUrl, '/') . $path;

        return $params !== [] ? $base . '?' . http_build_query($params) : $base;
    }

    private function assertSuccess(\Illuminate\Http\Client\Response $response, string $context): void
    {
        if ($response->successful()) {
            $data = $response->json();
            if (($data['success'] ?? false) === false) {
                $errors = json_encode($data['errors'] ?? []);
                throw new RuntimeException("Worker {$context} Fehler: {$errors}");
            }
            return;
        }

        if ($response->status() === 507) {
            $msg = $response->json('errors.0.message', 'Quota überschritten.');
            throw new RuntimeException("Worker Quota-Limit erreicht: {$msg}");
        }

        throw new RuntimeException(
            "Worker {$context} fehlgeschlagen [HTTP {$response->status()}]: " . $response->body(),
        );
    }
}
