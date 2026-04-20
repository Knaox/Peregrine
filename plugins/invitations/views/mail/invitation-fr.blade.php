<x-mail::layout>
    <x-slot:slot>
        <h1>Vous avez ete invite sur un serveur</h1>

        <p>
            <strong>{{ $inviterName }}</strong> vous a invite a rejoindre le serveur
            <strong>{{ $serverName }}</strong>.
        </p>

        @if(!empty($permissionLabels))
            <p>Vous aurez les permissions suivantes :</p>
            <ul>
                @foreach($permissionLabels as $label)
                    <li>{{ $label }}</li>
                @endforeach
            </ul>
        @endif

        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $acceptUrl }}" class="btn-primary">Accepter l'invitation</a>
        </p>

        <p class="text-muted">
            Cette invitation expire le {{ $expiresAt }}.
        </p>

        <p class="text-muted">
            Si vous n'attendiez pas cette invitation, vous pouvez ignorer cet email.
        </p>
    </x-slot:slot>

    <x-slot:appName>{{ $appName }}</x-slot:appName>
    <x-slot:logoUrl>{{ $logoUrl }}</x-slot:logoUrl>
</x-mail::layout>
