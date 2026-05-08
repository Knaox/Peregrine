<?php

return [
    'page' => [
        'title' => 'Webhook receiver',
        'navigation' => 'Webhook receiver',
        'subtitle' => 'Configure the token Pelican uses to push events.',
    ],
    'sections' => [
        'receiver' => 'Receiver',
        'receiver_description' => 'Toggle the public webhook endpoint on or off. When off, Pelican calls return 503 and no events are processed.',
        'token' => '1. Generate the bearer token',
        'token_description' => 'Pelican does not sign its webhooks — auth relies entirely on this token.',
        'configure' => '2. Configure Pelican (/admin/webhooks → Create Webhook)',
        'configure_description' => 'Top fields + headers + events to tick. The events list is grouped by priority — start with "Required", add others as needed.',
        'verify' => '3. Verify',
        'verify_description' => 'Once Pelican saves, every event lands here for audit.',
    ],
    'fields' => [
        'enabled' => 'Enable Pelican webhook receiver',
        'enabled_helper' => 'Disable to temporarily stop accepting webhook events without losing the configured token.',
        'token' => 'Pelican webhook authentication token',
        'token_helper' => 'Click the key icon to generate a fresh random 64-char token. Leave blank on save to keep the stored value (rotation requires updating the Pelican headers in lockstep).',
        'token_action_tooltip' => 'Generate a new random 64-char token',
        'top_fields' => 'Top fields',
        'headers' => 'Headers (keep the default row, add the second)',
        'events_required' => 'Required (install completion + lifecycle)',
        'events_required_note' => 'These five events are mandatory. `event: Server\\Installed` is the canonical end-of-install signal (Pelican fires it as soon as the install script finishes); `updated: Server` is the secondary signal (Pelican flips status from "installing" to null at the same moment) and acts as a safety net. Without these two, servers stay in "provisioning" forever and a "stuck" badge shows in /admin/servers. `created: Server` / `deleted: Server` / `created: User` are the standard lifecycle events.',
        'events_recommended' => 'Recommended (cuts manual sync)',
        'events_recommended_note' => 'Mirrors user email/name changes, node infrastructure, and egg/variable definitions in real time. With these ticked, the manual `sync:users / sync:nodes / sync:eggs` commands become safety nets you rarely need.',
        'events_blocklist' => 'DO NOT tick',
        'events_blocklist_note' => 'Allocation / Backup / Database / DatabaseHost / ServerTransfer / Subuser : Peregrine no longer has tables for these — the SPA reads them live from Pelican when the user opens /network, /databases, /backups, /sub-users. Ticking them feeds nothing on our side and the receiver records them as ignored. `Schedule` and `Task` fire on every cron tick (flood). `ActivityLog` fires on every user action (flood). `ApiKey` updates `last_used_at` on every API call (noise). `Webhook` / `WebhookConfiguration` create infinite loops.',
        'docs' => 'Step-by-step walkthrough',
        'docs_note' => 'Full setup guide, troubleshooting, known limits, and how the install-status sync interacts with Bridge modes.',
        'audit' => 'Live audit of received webhooks',
        'audit_note' => 'Every accepted webhook event with HTTP status, error message, and idempotency hash.',
    ],
    'header_descriptions' => [
        'pelican_default' => 'Pelican\'s default — keep it',
        'add_row' => 'Add this row',
    ],
    'header_values' => [
        'token_placeholder' => 'Bearer <token above>',
    ],
    'top_fields' => [
        'type' => 'Regular',
        'description' => 'Peregrine — Pelican webhook receiver',
    ],
    'notifications' => [
        'token_generated_title' => 'Token generated',
        'token_generated_body' => 'Copy it from the field, paste it in Pelican\'s webhook headers, then click Save.',
        'saved' => 'Pelican webhook settings saved',
    ],
];
