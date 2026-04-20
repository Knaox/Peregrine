<x-mail::layout>
    <x-slot:slot>
        <h1>You've been invited to a server</h1>

        <p>
            <strong>{{ $inviterName }}</strong> has invited you to join the server
            <strong>{{ $serverName }}</strong>.
        </p>

        @if(!empty($permissionLabels))
            <p>You will have the following permissions:</p>
            <ul>
                @foreach($permissionLabels as $label)
                    <li>{{ $label }}</li>
                @endforeach
            </ul>
        @endif

        <p style="text-align: center; margin: 24px 0;">
            <a href="{{ $acceptUrl }}" class="btn-primary">Accept Invitation</a>
        </p>

        <p class="text-muted">
            This invitation expires on {{ $expiresAt }}.
        </p>

        <p class="text-muted">
            If you didn't expect this invitation, you can safely ignore this email.
        </p>
    </x-slot:slot>

    <x-slot:appName>{{ $appName }}</x-slot:appName>
    <x-slot:logoUrl>{{ $logoUrl }}</x-slot:logoUrl>
</x-mail::layout>
