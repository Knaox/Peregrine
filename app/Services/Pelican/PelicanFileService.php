<?php

namespace App\Services\Pelican;

use App\Services\Pelican\Concerns\MakesClientRequests;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PelicanFileService
{
    use MakesClientRequests;

    /** @return array<int, array<string, mixed>> */
    public function listFiles(string $serverIdentifier, string $directory = '/'): array
    {
        return $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/files/list", ['directory' => $directory])
            ->throw()
            ->json('data') ?? [];
    }

    public function getFileContent(string $serverIdentifier, string $filePath): string
    {
        return $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/files/contents", ['file' => $filePath])
            ->throw()
            ->body();
    }

    public function renameFile(string $serverIdentifier, string $from, string $to): void
    {
        $root = dirname($from);
        $this->request()
            ->put("/api/client/servers/{$serverIdentifier}/files/rename", [
                'root' => $root === '.' ? '/' : $root,
                'files' => [['from' => basename($from), 'to' => $to]],
            ])->throw();
    }

    public function deleteFile(string $serverIdentifier, string $filePath): void
    {
        $root = dirname($filePath);
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/files/delete", [
                'root' => $root === '.' ? '/' : $root,
                'files' => [basename($filePath)],
            ])->throw();
    }

    /** @param string[] $files */
    public function compressFiles(string $serverIdentifier, array $files): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/files/compress", ['root' => '/', 'files' => $files])
            ->throw();
    }

    /**
     * Write file content via Pelican's `POST /files/write?file=...` endpoint.
     *
     * Pelican forwards the raw HTTP body to Wings as the new file content.
     * Content-Length must always be set — including `0` for the empty-file
     * case (the file manager's "New file" action). Some Guzzle builds drop
     * `Content-Length: 0` on POSTs with an empty body, which makes Wings
     * return 411 Length Required and the file is never created. Forcing
     * the header here covers that edge case.
     *
     * Pelican explicitly accepts an empty body — the `WriteFileContentRequest`
     * validator on their side has no rule on the body content, only on the
     * `file` query string parameter.
     */
    public function writeFile(string $serverIdentifier, string $filePath, string $content): void
    {
        Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->clientApiKey(),
            'Accept' => 'application/json',
            'Content-Type' => 'text/plain',
            'Content-Length' => (string) strlen($content),
        ])
            ->withBody($content, 'text/plain')
            ->retry(3, 100)
            ->baseUrl($this->baseUrl())
            ->post("/api/client/servers/{$serverIdentifier}/files/write?file=" . urlencode($filePath))
            ->throw();
    }

    public function decompressFiles(string $serverIdentifier, string $file): void
    {
        $this->request()->timeout(300)
            ->post("/api/client/servers/{$serverIdentifier}/files/decompress", [
                'root' => dirname($file) === '.' ? '/' : dirname($file),
                'file' => basename($file),
            ])->throw();
    }

    public function getUploadUrl(string $serverIdentifier): string
    {
        return $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/files/upload")
            ->throw()
            ->json('attributes.url') ?? '';
    }

    public function createFolder(string $serverIdentifier, string $root, string $name): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/files/create-folder", ['root' => $root, 'name' => $name])
            ->throw();
    }

    public function getFileDownloadUrl(string $serverIdentifier, string $file): string
    {
        return $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/files/download", ['file' => $file])
            ->throw()
            ->json('attributes.url') ?? '';
    }

    public function copyFile(string $serverIdentifier, string $location): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/files/copy", ['location' => $location])
            ->throw();
    }

    /**
     * Batch chmod. $files is an array of ['file' => string, 'mode' => int|string].
     *
     * @param array<int, array{file: string, mode: int|string}> $files
     */
    public function chmodFiles(string $serverIdentifier, string $root, array $files): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/files/chmod", [
                'root' => $root,
                'files' => $files,
            ])
            ->throw();
    }

    /**
     * Pull a remote URL into the server filesystem (daemon-side download).
     * `foreground=false` lets Wings stream in the background so the HTTP
     * call returns quickly; Peregrine's listing will pick it up on refresh.
     */
    public function pullFile(
        string $serverIdentifier,
        string $url,
        ?string $directory = null,
        ?string $filename = null,
        bool $useHeader = true,
    ): void {
        $payload = ['url' => $url, 'use_header' => $useHeader, 'foreground' => false];
        if ($directory !== null && $directory !== '') {
            $payload['directory'] = $directory;
        }
        if ($filename !== null && $filename !== '') {
            $payload['filename'] = $filename;
        }

        $this->request()->timeout(30)
            ->post("/api/client/servers/{$serverIdentifier}/files/pull", $payload)
            ->throw();
    }
}
