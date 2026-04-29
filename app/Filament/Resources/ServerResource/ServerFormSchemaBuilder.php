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
                        ->label(__('admin.fields.name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('user_id')
                        ->label(__('admin.fields.owner'))
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('status')
                        ->label(__('admin.fields.status'))
                        ->options([
                            'active' => __('admin.statuses.active'),
                            'running' => __('admin.statuses.running'),
                            'stopped' => __('admin.statuses.stopped'),
                            'suspended' => __('admin.statuses.suspended'),
                            'terminated' => __('admin.statuses.terminated'),
                            'provisioning' => __('admin.statuses.provisioning'),
                            'provisioning_failed' => __('admin.statuses.provisioning_failed'),
                            'offline' => __('admin.statuses.offline'),
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
                        ->label(__('admin.fields.pelican_server_id'))
                        ->numeric()
                        ->helperText(__('admin.servers.helpers.pelican_id')),
                    TextInput::make('idempotency_key')
                        ->label(__('admin.fields.idempotency_key'))
                        ->disabled()
                        ->helperText(__('admin.servers.helpers.idempotency')),
                    TextInput::make('provisioning_error')
                        ->label(__('admin.fields.last_provisioning_error'))
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
                        ->label(__('admin.fields.stripe_subscription_id'))
                        ->maxLength(255)
                        ->nullable()
                        ->helperText(__('admin.servers.helpers.stripe_subscription')),
                    TextInput::make('payment_intent_id')
                        ->label(__('admin.fields.payment_intent_id'))
                        ->maxLength(255)
                        ->nullable()
                        ->disabled()
                        ->helperText(__('admin.servers.helpers.payment_intent')),
                    \Filament\Forms\Components\DateTimePicker::make('scheduled_deletion_at')
                        ->label(__('admin.fields.scheduled_deletion_at'))
                        ->nullable()
                        ->helperText(__('admin.servers.helpers.scheduled_deletion')),
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
                ->label(__('admin.fields.egg'))
                ->relationship('egg', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText(__('admin.servers.helpers.egg')),
        ];

        if ($isShopStripe) {
            $fields[] = Select::make('plan_id')
                ->label(__('admin.fields.plan'))
                ->relationship('plan', 'name')
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText(__('admin.servers.helpers.plan'));
        }

        return $fields;
    }
}
