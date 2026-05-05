<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Enums;

enum ModpackProvider: string
{
    case Modrinth = 'modrinth';
    case CurseForge = 'curseforge';
    case Atlauncher = 'atlauncher';
    case Ftb = 'ftb';
    case Technic = 'technic';
    case VoidsWrath = 'voidswrath';

    public function displayName(): string
    {
        return match ($this) {
            self::Modrinth => 'Modrinth',
            self::CurseForge => 'CurseForge',
            self::Atlauncher => 'ATLauncher',
            self::Ftb => 'Feed The Beast',
            self::Technic => 'Technic',
            self::VoidsWrath => 'VoidsWrath',
        };
    }

    public function externalRegisterUrl(): ?string
    {
        return match ($this) {
            self::CurseForge => 'https://console.curseforge.com',
            default => null,
        };
    }
}
