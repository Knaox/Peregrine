<?php

namespace App\Filament\Resources\ServerResource;

use App\Services\Bridge\BridgeModeService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Builds the Filament form schema for ServerResource. Uses tabs to keep the
 * surface compact: Identity / Configuration / Provisioning / Billing.
 *
 * Billing tab is only registered in Shop+Stripe mode.
 */
final class ServerFormSchemaBuilder
{
    public static function build(Schema $schema): Schema
    {
        $isShopStripe = app(BridgeModeService::class)->isShopStripe();

        $tabs = [
            Tab::make(__('admin.tabs.identity'))
                ->icon('heroicon-o-identification')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('user_id')
                        ->label('Owner')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'running' => 'Running',
                            'stopped' => 'Stopped',
                            'suspended' => 'Suspended',
                            'terminated' => 'Terminated',
                            'provisioning' => 'Provisioning',
                            'provisioning_failed' => 'Provisioning failed',
                            'offline' => 'Offline',
                        ])
                        ->required(),
                ])->columns(2),

            Tab::make(__('admin.tabs.configuration'))
                ->icon('heroicon-o-cog-6-tooth')
                ->schema(self::configurationFields($isShopStripe))
                ->columns(2),

            Tab::make(__('admin.tabs.provisioning'))
                ->icon('heroicon-o-bolt')
                ->schema([
                    TextInput::make('pelican_server_id')
                        ->label('Pelican Server ID')
                        ->numeric()
                        ->helperText('Internal Pelican identifier. Changing this re-maps the local row to a different Pelican server.'),
                    TextInput::make('idempotency_key')
                        ->label('Idempotency key')
                        ->disabled()
                        ->helperText('Set by ProvisionServerJob — guarantees a single Pelican server per Stripe checkout.'),
                    TextInput::make('provisioning_error')
                        ->label('Last provisioning error')
                        ->disabled()
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])->columns(2),
        ];

        if ($isShopStripe) {
            $tabs[] = Tab::make(__('admin.tabs.billing'))
                ->icon('heroicon-o-credit-card')
                ->schema([
                    TextInput::make('stripe_subscription_id')
                        ->label('Stripe Subscription ID')
                        ->maxLength(255)
                        ->nullable()
                        ->helperText('Bound to the customer\'s active subscription. Cleared on cancellation.'),
                    TextInput::make('payment_intent_id')
                        ->label('Payment Intent ID')
                        ->maxLength(255)
                        ->nullable()
                        ->disabled()
                        ->helperText('Set automatically from the Stripe checkout — read-only.'),
                    \Filament\Forms\Components\DateTimePicker::make('scheduled_deletion_at')
                        ->label('Scheduled deletion at')
                        ->nullable()
                        ->helperText('Set when the customer cancels — server is hard-deleted at this date if not unsuspended.'),
                ])->columns(2);
        }

        return $schema->schema([
            Tabs::make('server-tabs')->tabs($tabs)->columnSpanFull(),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function configurationFields(bool $isShopStripe): array
    {
        $fields = [
            Select::make('egg_id')
                ->relationship('egg', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText('The Pelican egg used to provision this server. Determines the docker image and start command.'),
        ];

        if ($isShopStripe) {
            $fields[] = Select::make('plan_id')
                ->relationship('plan', 'name')
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText('Optional — link this server to a Shop plan for billing reconciliation.');
        }

        return $fields;
    }
}
