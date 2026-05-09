<?php

declare(strict_types=1);

namespace App\Filament\Resources\WebhookEndpointResource\Pages;

use App\Filament\Resources\WebhookEndpointResource;
use App\Models\WebhookEndpoint;
use App\Webhooks\StandardWebhookSigner;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EditWebhookEndpoint extends EditRecord
{
    protected static string $resource = WebhookEndpointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestEvent')
                ->label(__('admin/webhook_endpoints.actions.send_test'))
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (WebhookEndpoint $record): void {
                    $signer = app(StandardWebhookSigner::class);
                    $id = (string) Str::uuid();
                    $ts = (string) time();
                    $body = (string) json_encode([
                        'type' => 'webhook.ping',
                        'id' => $id,
                        'timestamp' => now()->toIso8601String(),
                        'data' => ['from' => 'peregrine-admin'],
                    ]);
                    $signature = $signer->sign($id, $ts, $body, (string) $record->signing_secret);

                    $start = microtime(true);
                    try {
                        $response = Http::timeout($record->timeout_seconds)
                            ->withHeaders([
                                'webhook-id' => $id,
                                'webhook-timestamp' => $ts,
                                'webhook-signature' => $signature,
                                'content-type' => 'application/json',
                                'user-agent' => 'Peregrine-Webhooks/1.0 (test)',
                            ])
                            ->withBody($body, 'application/json')
                            ->post($record->url);
                        $latency = (int) ((microtime(true) - $start) * 1000);
                        Notification::make()
                            ->title(__('admin/webhook_endpoints.actions.test_done'))
                            ->body('HTTP '.$response->status().' · '.$latency.' ms')
                            ->color($response->successful() ? 'success' : 'warning')
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('admin/webhook_endpoints.actions.test_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('rotateSecret')
                ->label(__('admin/webhook_endpoints.actions.rotate'))
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (WebhookEndpoint $record): void {
                    $secret = 'whsec_'.bin2hex(random_bytes(24));
                    $record->signing_secret = $secret;
                    $record->save();
                    Notification::make()
                        ->title(__('admin/webhook_endpoints.actions.rotated_title'))
                        ->body(__('admin/webhook_endpoints.actions.rotated_body', ['secret' => $secret]))
                        ->persistent()
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
