<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Queue-safe mail job for plugin Mailables.
 *
 * Stores only primitives (no plugin classes serialized).
 * The Mailable is reconstructed at execution time.
 * Plugin classes are available via plugins/autoload.php (loaded in bootstrap/app.php).
 */
class SendPluginMail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var int */
    public $tries = 3;

    /** @var array<int, int> */
    public $backoff = [10, 60, 300];

    /** @var bool */
    public $deleteWhenMissingModels = true;

    public function __construct(
        public readonly string $to,
        public readonly string $mailableClass,
        public readonly array $data,
    ) {}

    public function handle(): void
    {
        try {
            if (! class_exists($this->mailableClass)) {
                $this->fail(new \RuntimeException("Mailable class {$this->mailableClass} not found."));

                return;
            }

            $mailable = new ($this->mailableClass)(...array_values($this->data));

            Mail::to($this->to)->send($mailable);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }
}
