<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType;

use Pr0jectX\Px\PxApp;
use Pr0jectX\PxDrupal\Drupal;
use Robo\Result;

trait DrupalCommandTrait
{
    /**
     * Ask Drupal to setup a salt hash outside the project root.
     */
    protected function askDrupalSetupSaltHash(): void
    {
        $projectRootPath = PxApp::projectRootPath();

        if ($this->confirm('Store the Drupal salt hash outside the project root?', true)) {
            $this->taskWriteToFile("{$projectRootPath}/salt.txt")
                ->text($this->drupalGenerateSaltHash())
                ->run();

            $this->writeDrupalSettings(
                '/^\$settings\[\'hash_salt\'\].+;$/m',
                Drupal::loadSettingSnippet('settings.hash.txt')
            );
        }
    }

    /**
     * Ask Drupal to setup configuration outside the project root.
     */
    protected function askDrupalSetupConfiguration(): void
    {
        $projectRootPath = PxApp::projectRootPath();

        if ($this->confirm('Store Drupal configuration outside the project root?', true)) {
            if (!file_exists("{$projectRootPath}/config/default")) {
                $this->_mkdir("{$projectRootPath}/config/default");
            }
            $this->writeDrupalSettings(
                '/^\$settings\[\'config_sync_directory\'\].+;$/m',
                Drupal::loadSettingSnippet('settings.config.txt')
            );
        }
    }

    /**
     * Ask to disable module updates via the Drupal UI.
     */
    protected function askDrupalDisableModuleUpdate(): void
    {
        if ($this->confirm('Disable installing/updating modules using the Drupal UI?', true)) {
            $this->writeDrupalSettings(
                '/^\$settings\[\'allow_authorize_operations\'\].+;$/m',
                Drupal::loadSettingSnippet('settings.authorize.txt')
            );
        }
    }

    /**
     * Write to the projects Drupal settings file.
     *
     * @param string $pattern
     *   The append unless matched pattern.
     * @param string $contents
     *   The Drupal settings content to append.
     * @param bool $isLocal
     *   If true write the contents to the local Drupal settings.
     *
     * @return \Robo\Result
     */
    protected function writeDrupalSettings(
        string $pattern,
        string $contents,
        bool $isLocal = false
    ): Result {
        return $this->taskWriteToFile($this->drupalProjectSettingsPath($isLocal))
            ->append()
            ->appendUnlessMatches($pattern, $contents)
            ->run();
    }
}
