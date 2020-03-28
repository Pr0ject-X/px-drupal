<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\Commands;

use Pr0jectX\Px\ProjectX\Plugin\PluginCommandTaskBase;
use Pr0jectX\PxDrupal\DrupalCommandResolver;

/**
 * Define the Drupal commands.
 */
class DrupalCommands extends PluginCommandTaskBase
{
    /**
     * @var string
     */
    const DRUPAL_UPDATE_URL = 'https://updates.drupal.org/release-history';

    /**
     * Execute arbitrary command.
     *
     * @aliases drupal
     *
     * @param array $cmd
     *   The arbitrary command.
     */
    public function drupalExec(array $cmd)
    {
        $this->runDrupalCommand('exec', [trim(implode(' ', $cmd))]);
    }

    /**
     * Install a new Drupal module.
     *
     * @param string $module
     *   The Drupal module machine name.
     *
     * @aliases drupal:mi
     */
    public function drupalModuleInstall(string $module)
    {
        if ($this->drupalModuleExist($module, '8.x')) {
            $result = $this->taskComposerRequire()
                ->arg("drupal/{$module}")
                ->run();

            if ($result->wasSuccessful()) {
                $this->runDrupalCommand(
                    'moduleInstall',
                    [$module]
                );
            }
        } else {
            throw new \InvalidArgumentException(
                sprintf("The Drupal module %s is invalid. Please refer to Drupal.org.", $module)
            );
        }
    }

    /**
     * Remove an installed Drupal module.
     *
     * @param string $module
     *   The Drupal module machine name.
     *
     * @aliases drupal:mr
     */
    public function drupalModuleRemove(string $module)
    {
        if ($this->drupalModuleExist($module, '8.x')) {
            $results = $this->runDrupalCommand(
                'moduleRemove',
                [$module]
            );

            /** @var \Robo\Result $result */
            if ($result = reset($results)) {
                if (!$result->wasSuccessful()) {
                    return;
                }
                $this->taskComposerRemove()->arg("drupal/{$module}")->run();
            }
        } else {
            throw new \InvalidArgumentException(
                sprintf("The Drupal module %s is invalid. Please refer to Drupal.org.", $module)
            );
        }
    }

    /**
     * Login to the Drupal application.
     */
    public function drupalLogin()
    {
        $this->runDrupalCommand('login');
    }

    /**
     * Create local Drupal user account.
     *
     * @param string $username
     *   The account user name.
     * @param array $opts
     * @option $role
     *   The account user role name
     * @option $email
     *   The account user email address.
     * @option $password
     *   The account user password.
     *
     * @aliases drupal:ca
     */
    public function drupalCreateAccount(
        string $username = 'admin',
        array $opts = [
            'role' => 'administrator',
            'email' => 'admin@example.com',
            'password' => 'admin',
        ]
    ) {
        $this->runDrupalCommand(
            'createAccount',
            [$username, $opts]
        );
    }

    /**
     * Rebuild the Drupal cache.
     *
     * @aliases drupal:cr, drupal:cc
     */
    public function drupalCacheRebuild()
    {
        $this->runDrupalCommand('cacheRebuild');
    }

    /**
     * Run Drupal command.
     *
     * @param string $method
     *   The Drupal command method.
     * @param array $args
     *   An array of Drupal command arguments.
     *
     * @return array
     *   Return true if the Drupal command ran; otherwise false.
     */
    protected function runDrupalCommand(string $method, array $args = []): array
    {
        $results = [];

        if ($envExecCommand = $this->findCommand('env:execute')) {
            $provider = $this->plugin->drupalProviderInstance();
            if ($commands = (array) (new DrupalCommandResolver($provider))->exec($method, $args)) {
                $results = [];
                foreach ($commands as $command) {
                    $symfonyCommand = $this->taskSymfonyCommand($envExecCommand);
                    $results[] = $symfonyCommand
                        ->arg('cmd', $command)
                        ->run();
                }
                return $results;
            }
        }

        return $results;
    }

    /**
     * Check if the Drupal module exist.
     *
     * @param string $module
     *   The Drupal machine name.
     * @param string $drupal_version
     *   The Drupal version (7.x, 8.x, 9.x)
     *
     * @return bool
     *   Return true if the Drupal module exist; otherwise false.
     */
    protected function drupalModuleExist(string $module, string $drupal_version): bool
    {
        if ($drupalReleaseXml = file_get_contents(self::DRUPAL_UPDATE_URL . "/{$module}/{$drupal_version}")) {
            return (new \SimpleXMLElement($drupalReleaseXml))->project_status == 'published';
        }

        return false;
    }
}
