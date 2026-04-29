<?php

namespace App\Actions\Settings;

use Illuminate\Support\Facades\Mail;

/**
 * Sends a test email using the currently configured SMTP settings, so an
 * admin can verify their config works without leaving the Filament page.
 *
 * Extracted from `App\Filament\Pages\Settings::testSmtp()` to keep that
 * page file under the 300-line plafond CLAUDE.md.
 */
final class TestSmtpConfigAction
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function execute(string $recipient): array
    {
        if ($recipient === '') {
            return ['ok' => false, 'message' => 'No recipient email available.'];
        }

        try {
            Mail::raw(
                'This is a test email from ' . config('app.name', 'Peregrine') . ".\n\nIf you received this, your SMTP is working.\n\nSent at: " . now()->toDateTimeString(),
                function ($message) use ($recipient): void {
                    $message->to($recipient)->subject(config('app.name', 'Peregrine') . ' — SMTP Test');
                },
            );

            return ['ok' => true, 'message' => "A test email was sent to {$recipient}."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
