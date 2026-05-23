<?php

/**
 * Backend message strings for the Invitations plugin.
 *
 * Used by the API + public routes to surface translated errors to
 * the frontend without leaking i18n logic into the SPA bundle.
 * Loaded via `loadTranslationsFrom(__DIR__.'/../lang', 'invitations')`
 * in InvitationsServiceProvider — frontend can render these directly
 * (they're already localized by the SetUserLocale middleware) or use
 * the `error_code` field for client-side translation if it prefers.
 */

return [
    'errors' => [
        'invitation_not_found' => 'Invitation not found or expired.',
        'email_mismatch' => 'Email does not match the invitation.',
        'already_accepted' => 'Invitation has already been accepted.',
        'already_revoked' => 'Invitation has been revoked.',
        'account_exists' => 'An account with this email already exists. Please log in.',
        'self_invite' => 'You cannot invite yourself.',
        'invalid_token' => 'Invitation not found.',
        'self_modify' => 'You cannot modify your own permissions.',
        'self_remove' => 'You cannot remove yourself.',
        'accept_failed' => 'Could not accept the invitation right now. Please try again in a moment.',
    ],
    'success' => [
        'invitation_sent' => 'Invitation sent.',
        'invitation_resent' => 'Invitation resent.',
        'invitation_revoked' => 'Invitation revoked.',
        'invitation_updated' => 'Invitation updated.',
        'permissions_updated' => 'Permissions updated.',
        'account_created_and_accepted' => 'Account created and invitation accepted.',
    ],
];
