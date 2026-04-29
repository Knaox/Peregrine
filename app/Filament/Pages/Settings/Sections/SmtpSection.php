<?php

namespace App\Filament\Pages\Settings\Sections;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

final class SmtpSection
{
    public static function make(): Section
    {
        return Section::make('SMTP / Email')
            ->description('Configure the mail server used to send emails (invitations, notifications).')
            ->icon('heroicon-o-envelope')
            ->schema([
                Select::make('mail_mailer')
                    ->label('Mail Driver')
                    ->options([
                        'smtp' => 'SMTP',
                        'log' => 'Log (development only)',
                        'sendmail' => 'Sendmail',
                    ])
                    ->default('smtp')
                    ->live(),
                TextInput::make('mail_host')
                    ->label('SMTP Host')
                    ->placeholder('mail.example.com')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_port')
                    ->label('SMTP Port')
                    ->placeholder('587')
                    ->maxLength(10)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                Select::make('mail_encryption')
                    ->label('Encryption')
                    ->options([
                        'tls' => 'TLS (recommended)',
                        'ssl' => 'SSL',
                        '' => 'None',
                    ])
                    ->default('tls')
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_username')
                    ->label('SMTP Username')
                    ->placeholder('noreply@example.com')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_password')
                    ->label('SMTP Password')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_from_address')
                    ->label('From Address')
                    ->placeholder('noreply@example.com')
                    ->email()
                    ->maxLength(255),
                TextInput::make('mail_from_name')
                    ->label('From Name')
                    ->placeholder('Peregrine')
                    ->maxLength(255),
            ])->columns(2);
    }
}
