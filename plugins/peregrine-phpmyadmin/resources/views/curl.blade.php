<div style="font-size:0.85rem;line-height:1.6;">
    <p style="margin:0 0 0.5rem;opacity:0.85;">
        {{ __('peregrine-phpmyadmin::messages.settings.test_curl_help') }}
    </p>
    @include('peregrine-phpmyadmin::partials.copy-block', ['code' => $curl, 'filename' => 'curl'])
</div>
