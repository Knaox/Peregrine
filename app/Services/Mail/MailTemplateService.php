<?php

namespace App\Services\Mail;

use App\Services\SettingsService;

/**
 * Resolves an email template to a ready-to-send {subject, body_html} pair.
 *
 * Lookup order:
 *   1. admin override in the settings table
 *      (email_tpl_{id}_subject_{locale}, email_tpl_{id}_body_{locale})
 *   2. default from MailTemplateRegistry
 *
 * Variable tokens written as {name}, {app_name}, {provider}, … are replaced
 * by the provided context. Unknown tokens pass through unchanged.
 */
class MailTemplateService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array{subject: string, body_html: string}
     */
    public function render(string $templateId, string $locale, array $variables): array
    {
        $locale = in_array($locale, ['en', 'fr'], true) ? $locale : 'en';
        $template = MailTemplateRegistry::find($templateId);

        if ($template === null) {
            // Unknown template — fail open with an empty shell so the caller
            // still sends something (better than bubbling to a 500 error in
            // the mail layer).
            return ['subject' => '', 'body_html' => ''];
        }

        $subject = $this->settings->get("email_tpl_{$templateId}_subject_{$locale}", null)
            ?? $template["default_subject_{$locale}"];
        $body = $this->settings->get("email_tpl_{$templateId}_body_{$locale}", null)
            ?? $template["default_body_{$locale}"];

        // Always enrich the context with app_name so templates can reference
        // {app_name} without the caller having to pass it each time.
        $variables['app_name'] = $variables['app_name'] ?? (string) $this->settings->get('app_name', 'Peregrine');

        return [
            'subject' => $this->substitute((string) $subject, $variables),
            'body_html' => $this->substitute((string) $body, $variables),
        ];
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    private function substitute(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = $value === null ? '' : (string) $value;
        }

        return strtr($template, $replacements);
    }
}
