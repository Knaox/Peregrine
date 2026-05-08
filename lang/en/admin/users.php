<?php

return [
    'resource' => [
        'label' => 'User',
        'plural' => 'Users',
        'navigation' => 'Users',
    ],
    'sections' => [
        'identity' => 'Identity',
    ],
    'helpers' => [
        'password' => 'Minimum 8 characters. Hashed with bcrypt before storage.',
        'admin' => 'Grants access to /admin and elevates Gate::before whitelist (Server only).',
        'pelican_user_id' => 'Manual override — changing this re-maps the user to a different Pelican account without touching Pelican.',
        'stripe_customer_id' => 'Set automatically by the first Stripe checkout. Edit only to fix a mismatch.',
        'sync_pelican' => 'If this user is synced, push the new password to the Pelican account as well.',
    ],
    'tooltips' => [
        'pelican_linked' => 'Linked to Pelican user #:id',
        'pelican_unlinked' => 'No Pelican account yet — use the Link action to provision one.',
    ],
    'link_pelican' => [
        'label' => 'Link to Pelican',
        'modal_heading' => 'Provision a Pelican account',
        'modal_description' => 'Dispatches a background job that finds the user in Pelican by email, or creates one if missing. Safe to retry — the job is idempotent.',
        'notification_title' => 'Link job dispatched',
        'notification_body' => 'Background job queued for :email. Refresh in a few seconds to see the linked status.',
    ],
    'change_password' => [
        'label' => 'Change password',
        'sync_pelican' => 'Also update on Pelican',
        'success' => 'Password changed',
        'partial_failure_title' => 'Local password updated, Pelican sync failed',
        'partial_failure_body' => 'The local password was changed but Pelican could not be updated. Check the logs.',
    ],
    'sync' => [
        'label' => 'Sync users',
        'modal_heading' => 'Sync users from Pelican',
        'modal_description' => 'Fetches all users from Pelican and imports any new ones into Peregrine.',
        'no_new' => 'No new users found',
        'imported' => 'Imported :count users from Pelican',
    ],
    'back_to_list' => 'Back to list',
];
