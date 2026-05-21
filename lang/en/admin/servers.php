<?php

return [
    'resource' => [
        'label' => 'Server',
        'plural' => 'Servers',
        'navigation' => 'Servers',
    ],
    'tooltips' => [
        'stuck' => 'This server has been awaiting the Pelican install-completion webhook for over 30 minutes. Most likely the events `event: Server\\Installed` and `updated: Server` are not ticked in your Pelican /admin/webhooks. Check /admin/pelican-webhook-logs for incoming events and /docs/pelican-webhook for the configuration guide.',
        'scheduled_deletion' => 'This server will be hard-deleted at the date shown. Use the action menu → Cancel scheduled deletion to keep it.',
    ],
    'helpers' => [
        'pelican_id' => 'Internal Pelican identifier. Changing this re-maps the local row to a different Pelican server.',
        'idempotency' => 'Set by ProvisionServerJob — guarantees a single Pelican server per Stripe checkout.',
        'stripe_subscription' => 'Bound to the customer\'s active subscription. Cleared on cancellation.',
        'payment_intent' => 'Set automatically from the Stripe checkout — read-only.',
        'scheduled_suspension' => 'Date the server will be suspended (end of the paid period). Editable to test the flow.',
        'scheduled_deletion' => 'Set when the customer cancels — server is hard-deleted at this date if not unsuspended.',
        'egg' => 'The Pelican egg used to provision this server. Determines the docker image and start command.',
        'plan' => 'Optional — link this server to a Shop plan for billing reconciliation.',
    ],
    'status_change' => [
        'pelican_failed' => 'Pelican action failed (suspend/unsuspend). The status was not changed.',
    ],
    'retry' => [
        'label' => 'Retry provisioning',
        'modal_heading' => 'Retry provisioning for ":name"?',
        'modal_description' => 'Re-dispatches a ProvisionServerJob with the same idempotency key — the local row is reused, no duplicate is created. Status flips back to "provisioning". Make sure the queue worker is running, otherwise the job will sit in `jobs` indefinitely.',
        'submit' => 'Retry now',
        'notification_title' => 'Retry dispatched',
        'notification_body' => 'ProvisionServerJob queued for ":name". Watch the queue logs for progress.',
    ],
    'cancel_deletion' => [
        'label' => 'Cancel scheduled deletion',
        'modal_heading' => 'Cancel scheduled deletion for ":name"?',
        'modal_description' => 'Hard deletion is currently scheduled for :date. Cancelling will keep the server in suspended state. To re-enable the customer\'s access, also unsuspend it from Pelican.',
        'submit' => 'Yes, keep this server',
        'notification_title' => 'Scheduled deletion cancelled',
        'notification_body' => 'Server ":name" will not be hard-deleted. It remains suspended — unsuspend manually if the customer regains access.',
    ],
    'sync' => [
        'label' => 'Sync servers',
        'modal_heading' => 'Sync servers from Pelican',
        'modal_description' => 'Fetches all servers from Pelican and imports any new ones into Peregrine.',
        'no_new' => 'No new servers found',
        'imported' => 'Imported :count servers from Pelican',
    ],
    'back_to_list' => 'Back to list',
];
