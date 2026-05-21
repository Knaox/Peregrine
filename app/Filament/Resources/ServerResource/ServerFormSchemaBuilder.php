<?php

namespace App\Filament\Resources\ServerResource;

use App\Services\Integrations\IntegrationStatusService;
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
        $isShopStripe = app(IntegrationStatusService::class)->hasStripeConfigured();

        $tabs = [
            Tab::make(__('admin/_shell.tabs.identity'))
                ->icon('heroicon-o-identification')
                ->schema([
                    TextInput::make('name')
                        ->label(__('admin/_shell.fields.name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('user_id')
                        ->label(__('admin/_shell.fields.owner'))
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    // Only the manageable lifecycle states. The runtime/power
                    // states (running/stopped/offline) and the transient
                    // provisioning states are sync-managed and not set by hand;
                    // they are collapsed to "active" on fill (EditServer).
                    Select::make('status')
                        ->label(__('admin/_shell.fields.status'))
                        ->options([
                            'active' => __('admin/_shell.statuses.active'),
                            'suspended' => __('admin/_shell.statuses.suspended'),
                            'terminated' => __('admin/_shell.statuses.terminated'),
                        ])
                        ->required()
                        ->helperText(__('admin/servers.helpers.status')),
                ])->columns(2),

            Tab::make(__('admin/_shell.tabs.configuration'))
                ->icon('heroicon-o-cog-6-tooth')
                ->schema(self::configurationFields($isShopStripe))
                ->columns(2),

            Tab::make(__('admin/_shell.tabs.provisioning'))
                ->icon('heroicon-o-bolt')
                ->schema([
                    TextInput::make('pelican_server_id')
                        ->label(__('admin/_shell.fields.pelican_server_id'))
                        ->numeric()
                        ->helperText(__('admin/servers.helpers.pelican_id')),
                    TextInput::make('idempotency_key')
                        ->label(__('admin/_shell.fields.idempotency_key'))
                        ->disabled()
                        ->helperText(__('admin/servers.helpers.idempotency')),
                    TextInput::make('provisioning_error')
                        ->label(__('admin/_shell.fields.last_provisioning_error'))
                        ->disabled()
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])->columns(2),
        ];

        if ($isShopStripe) {
            $tabs[] = Tab::make(__('admin/_shell.tabs.billing'))
                ->icon('heroicon-o-credit-card')
                ->schema([
                    TextInput::make('stripe_subscription_id')
                        ->label(__('admin/_shell.fields.stripe_subscription_id'))
                        ->maxLength(255)
                        ->nullable()
                        ->helperText(__('admin/servers.helpers.stripe_subscription')),
                    TextInput::make('payment_intent_id')
                        ->label(__('admin/_shell.fields.payment_intent_id'))
                        ->maxLength(255)
                        ->nullable()
                        ->disabled()
                        ->helperText(__('admin/servers.helpers.payment_intent')),
                    \Filament\Forms\Components\DateTimePicker::make('scheduled_suspension_at')
                        ->label(__('admin/_shell.fields.scheduled_suspension_at'))
                        ->nullable()
                        ->helperText(__('admin/servers.helpers.scheduled_suspension')),
                    \Filament\Forms\Components\DateTimePicker::make('scheduled_deletion_at')
                        ->label(__('admin/_shell.fields.scheduled_deletion_at'))
                        ->nullable()
                        ->helperText(__('admin/servers.helpers.scheduled_deletion')),
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
                ->label(__('admin/_shell.fields.egg'))
                ->relationship('egg', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText(__('admin/servers.helpers.egg')),
        ];

        if ($isShopStripe) {
            $fields[] = Select::make('server_configuration_id')
                ->label(__('admin/_shell.fields.configuration'))
                ->relationship('serverConfiguration', 'internal_name')
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText(__('admin/servers.helpers.configuration'));
        }

        return $fields;
    }
}
