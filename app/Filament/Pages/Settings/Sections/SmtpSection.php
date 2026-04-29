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
        return Section::make(__('admin.settings_form.smtp.section'))
            ->description(__('admin.settings_form.smtp.description'))
            ->icon('heroicon-o-envelope')
            ->schema([
                Select::make('mail_mailer')
                    ->label(__('admin.settings_form.smtp.driver'))
                    ->options([
                        'smtp' => __('admin.settings_form.smtp.driver_options.smtp'),
                        'log' => __('admin.settings_form.smtp.driver_options.log'),
                        'sendmail' => __('admin.settings_form.smtp.driver_options.sendmail'),
                    ])
                    ->default('smtp')
                    ->live(),
                TextInput::make('mail_host')
                    ->label(__('admin.settings_form.smtp.host'))
                    ->placeholder('mail.example.com')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_port')
                    ->label(__('admin.settings_form.smtp.port'))
                    ->placeholder('587')
                    ->maxLength(10)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                Select::make('mail_encryption')
                    ->label(__('admin.settings_form.smtp.encryption'))
                    ->options([
                        'tls' => __('admin.settings_form.smtp.encryption_options.tls'),
                        'ssl' => __('admin.settings_form.smtp.encryption_options.ssl'),
                        '' => __('admin.settings_form.smtp.encryption_options.none'),
                    ])
                    ->default('tls')
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_username')
                    ->label(__('admin.settings_form.smtp.username'))
                    ->placeholder('noreply@example.com')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_password')
                    ->label(__('admin.settings_form.smtp.password'))
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('mail_mailer') === 'smtp'),
                TextInput::make('mail_from_address')
                    ->label(__('admin.settings_form.smtp.from_address'))
                    ->placeholder('noreply@example.com')
                    ->email()
                    ->maxLength(255),
                TextInput::make('mail_from_name')
                    ->label(__('admin.settings_form.smtp.from_name'))
                    ->placeholder('Peregrine')
                    ->maxLength(255),
            ])->columns(2);
    }
}
