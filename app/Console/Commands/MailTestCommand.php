<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {email : The email address to send a test mail to}';

    protected $description = 'Send a test email to verify SMTP configuration';

    public function handle(): int
    {
        $email = $this->argument('email');
        $appName = config('app.name', 'Peregrine');

        $this->info("Sending test email to {$email}...");

        try {
            Mail::raw(
                "This is a test email from {$appName}.\n\nIf you received this, your SMTP configuration is working correctly.\n\nSent at: " . now()->toDateTimeString(),
                function ($message) use ($email, $appName): void {
                    $message->to($email)
                        ->subject("{$appName} — SMTP Test");
                },
            );

            $this->info('Test email sent successfully.');
            $this->line('Check your inbox (and spam folder) for the email.');
            $this->newLine();
            $this->line('To verify deliverability, use: https://www.mail-tester.com');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to send test email: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
