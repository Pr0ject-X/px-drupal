<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal;

/**
 * Define reusable Drupal commands.
 */
class Drupal
{
    /**
     * @var string
     */
    protected const DRUPAL_UPDATE_URL = 'https://updates.drupal.org/release-history';

    /**
     * Print the Drupal banner.
     */
    public static function printBanner(): void
    {
        print file_get_contents(dirname(__DIR__) . '/banner.txt');
    }

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
     * Check if the Drupal module exist.
     *
     * @param $modules
     *   A single or array of Drupal modules.
     * @param string $version
     *   The Drupal version (7.x, 8.x, 9.x)
     *
     * @return bool
     *   Return true if the Drupal module exist; otherwise false.
     */
    public static function moduleExist(
        $modules,
        string $version
    ): bool {
        if (!is_array($modules)) {
            $modules = [$modules];
        }

        foreach ($modules as $module) {
            $module = isset($module['name']) ? $module['name'] : $module;

            if ($drupalReleaseXml = file_get_contents(self::DRUPAL_UPDATE_URL . "/{$module}/{$version}")) {
                $xml = new \SimpleXMLElement($drupalReleaseXml);

                if ($xml->project_status != 'published') {
                    return false;
                }
            }
        }

        return true;
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

    /**
     * Load the template file contents.
     *
     * @param string $filename
     *   The filename to the template to load.
     *
     * @return string
     *   The loaded template file path.
     */
    protected static function loadTemplateFile(string $filename): string
    {
        return file_get_contents(static::getTemplatePath($filename));
    }

    /**
     * Get the template path.
     *
     * @param string $filename
     *   The filename to template path to retrieve.
     *
     * @return string
     *   The full path to the template file.
     */
    protected static function getTemplatePath(string $filename): string
    {
        $baseTemplateDir = dirname(__DIR__) . '/templates';
        $templateFullPath = "{$baseTemplateDir}/{$filename}";

        if (!file_exists($templateFullPath)) {
            throw new \RuntimeException(
                sprintf('Unable to locate the %s template file!', $filename)
            );
        }

        return $templateFullPath;
    }
}
