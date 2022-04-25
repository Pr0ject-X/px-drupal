<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal;

use Pr0jectX\Px\DefaultPluginFoundation;

/**
 * Define reusable Drupal commands.
 */
class Drupal extends DefaultPluginFoundation
{
    /**
     * Get the Drupal default install options.
     *
     * @return array
     *   An array of Drupal install options.
     */
    public static function defaultInstallOptions(): array
    {
        return [
            'site-name' => 'Drupal Project',
            'site-mail' => 'admin@example.com',
            'account-name' => 'admin',
            'account-pass' => 'admin',
            'account-mail' => 'admin@example.com',
        ];
    }

    /**
     * Load the Drupal setting file base.
     *
     * @return string
     *   The Drupal setting file base template.
     */
    public static function loadSettingBase(): string
    {
        return static::loadTemplateFile('settings.php.txt');
    }

    /**
     * Load the Drupal setting snippet.
     *
     * @param string $filename
     *   The Drupal setting snippet filename.
     *
     * @return string
     *   The Drupal snippet setting content.
     */
    public static function loadSettingSnippet(string $filename): string
    {
        return static::loadTemplateFile("snippets/{$filename}");
    }
}
