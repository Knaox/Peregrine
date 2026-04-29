<?php

namespace App\Filament\Resources\ServerResource;

use App\Services\Bridge\BridgeModeService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Builds the Filament form schema for ServerResource. Extracted to keep
 * the parent Resource under the 300-line plafond CLAUDE.md.
 *
 * Composition:
 *   - "Server Details" section (always)
 *   - "Configuration" section (always)
 *   - "Billing" section (Shop+Stripe mode only)
 */
final class ServerFormSchemaBuilder
{
    public static function build(Schema $schema): Schema
    {
        $isShopStripe = app(BridgeModeService::class)->isShopStripe();

        $configurationFields = [
            Select::make('egg_id')
                ->relationship('egg', 'name')
                ->searchable()
                ->preload()
                ->required(),
        ];

        // Plans only exist in Shop+Stripe mode (Shop pushes the catalogue).
        // Hide the picker entirely in Disabled / Paymenter modes.
        if ($isShopStripe) {
            $configurationFields[] = Select::make('plan_id')
                ->relationship('plan', 'name')
                ->searchable()
                ->preload()
                ->nullable();
        }

        $sections = [
            Section::make('Server Details')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Select::make('user_id')
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
                            'offline' => 'Offline',
                        ])
                        ->required(),
                    TextInput::make('pelican_server_id')
                        ->label('Pelican Server ID')
                        ->numeric(),
                ])->columns(2),

            Section::make('Configuration')
                ->schema($configurationFields)
                ->columns(2),
        ];

        // Billing section (Stripe Subscription ID + Payment Intent ID) is
        // meaningless outside Shop+Stripe mode — hide the whole section.
        if ($isShopStripe) {
            $sections[] = Section::make('Billing')
                ->schema([
                    TextInput::make('stripe_subscription_id')
                        ->label('Stripe Subscription ID')
                        ->maxLength(255)
                        ->nullable(),
                    TextInput::make('payment_intent_id')
                        ->label('Payment Intent ID')
                        ->maxLength(255)
                        ->nullable()
                        ->disabled(),
                ])->columns(2);
        }

        return $schema->schema($sections);
    }
}
