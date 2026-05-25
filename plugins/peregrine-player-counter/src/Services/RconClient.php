<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use RuntimeException;

/**
 * Minimal one-shot Source RCON client (the protocol ARK and Source-engine games
 * speak): connect, authenticate, run a single command, return the response
 * text. Throws on connect/auth/read failure. No persistent connection.
 */
class RconClient
{
    private const TYPE_AUTH = 3;

    private const TYPE_EXEC = 2;

    private const TYPE_AUTH_RESPONSE = 2;

    public function command(string $host, int $port, string $password, string $command, float $timeout = 4.0): string
    {
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp === false) {
            throw new RuntimeException("connect failed: {$errstr}");
        }

        stream_set_timeout($fp, max(1, (int) ceil($timeout)));

        try {
            $this->send($fp, 1, self::TYPE_AUTH, $password);
            if (! $this->awaitAuth($fp)) {
                throw new RuntimeException('auth failed (bad RCON password)');
            }

            $this->send($fp, 2, self::TYPE_EXEC, $command);
            [, , $body] = $this->receive($fp);

            return $body;
        } finally {
            fclose($fp);
        }
    }

    private function send($fp, int $id, int $type, string $body): void
    {
        $payload = pack('VV', $id, $type).$body."\x00\x00";
        fwrite($fp, pack('V', strlen($payload)).$payload);
    }

    /**
     * Read packets until the auth response. Source replies with id -1 on a bad
     * password, or the request id on success (a stray empty value packet may
     * precede it, so skip non-auth-response packets).
     */
    private function awaitAuth($fp): bool
    {
        for ($i = 0; $i < 4; $i++) {
            [$id, $type] = $this->receive($fp);
            if ($type === self::TYPE_AUTH_RESPONSE) {
                return $id !== -1;
            }
        }

        return false;
    }

    /**
     * @return array{0: int, 1: int, 2: string} [id, type, body]
     */
    private function receive($fp): array
    {
        $sizeRaw = $this->readBytes($fp, 4);
        if (strlen($sizeRaw) < 4) {
            throw new RuntimeException('short read');
        }
        $size = (int) unpack('V', $sizeRaw)[1];
        if ($size < 10 || $size > 65536) {
            throw new RuntimeException('invalid packet size');
        }

        $data = $this->readBytes($fp, $size);
        $id = (int) unpack('V', substr($data, 0, 4))[1];
        if ($id >= 2147483648) {
            $id -= 4294967296;
        }
        $type = (int) unpack('V', substr($data, 4, 4))[1];
        $body = substr($data, 8, -2);

        return [$id, $type, $body];
    }

    private function readBytes($fp, int $len): string
    {
        $buf = '';
        while (strlen($buf) < $len) {
            $chunk = fread($fp, $len - strlen($buf));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($fp);
                if (! empty($meta['timed_out'])) {
                    throw new RuntimeException('read timeout');
                }
                break;
            }
            $buf .= $chunk;
        }

        return $buf;
    }
}
