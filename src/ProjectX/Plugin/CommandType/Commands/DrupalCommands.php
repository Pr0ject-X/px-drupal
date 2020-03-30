<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\Commands;

use Pr0jectX\Px\ProjectX\Plugin\PluginCommandTaskBase;
use Pr0jectX\Px\PxApp;
use Pr0jectX\PxDrupal\Drupal;
use Pr0jectX\PxDrupal\DrupalCommandResolver;
use Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\DrupalCommandTrait;
use Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\DrupalCommandType;

/**
 * Define the Drupal commands.
 */
class DrupalCommands extends PluginCommandTaskBase
{
    use DrupalCommandTrait;

    /**
     * @var string
     */
    protected const DRUPAL_UPDATE_URL = 'https://updates.drupal.org/release-history';

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
     * Install a fresh Drupal database.
     *
     * @param string $profile
     *   The Drupal install profile to use.
     * @param array $opts
     *
     * @option $site-name
     *   The Drupal site name.
     * @option $site-mail
     *   The Drupal site email address.
     * @option $account-name
     *   The Drupal superuser account name.
     * @option $account-pass
     *   The Drupal superuser account password.
     * @option $account-mail
     *   The Drupal superuser account email address.
     */
    public function drupalInstall($profile = 'standard', array $opts = [
        'site-name' => 'Drupal Project',
        'site-mail' => 'admin@example.com',
        'account-name' => 'admin',
        'account-pass' => 'admin',
        'account-mail' => 'admin@example.com',
    ]): void
    {
        Drupal::printBanner();

        if (
            PxApp::composerHasPackage('drupal/core')
            || PxApp::composerHasPackage('drupal/core-recommended')
        ) {
            $settingsFilePath = $this->drupalProjectSettingsPath();

            if (!file_exists($settingsFilePath)) {
                $this->taskWriteToFile($settingsFilePath)
                ->text(Drupal::loadSettingBase())
                ->run();
            }
            $this->askDrupalSetupSaltHash();
            $this->askDrupalSetupConfiguration();
            $this->askDrupalDisableModuleUpdate();

            $this->taskSymfonyCommand($this->findCommand('drupal:local-setup'))
              ->run();

            $installOptions = Drupal::defaultInstallOptions();

            foreach (array_keys($installOptions) as $property) {
                $label = strtr($property, '-', ' ');
                $opts[$property] = $this->askDefault(
                    sprintf('Input the %s', $label),
                    $opts[$property]
                );
            }
            $options = array_intersect_key($opts, $installOptions);

            $results = $this->runDrupalCommand(
                'install',
                [$this->drupalProjectDatabase()->databaseUrl(), $profile, $options]
            );
            $result = reset($results);

            if ($result->wasSuccessful()) {
                $this->success('Drupal was successfully installed!');

                if ($this->confirm('Do you want to launch the Drupal application?', true)) {
                    $this->taskSymfonyCommand($this->findCommand('env:launch'))->run();
                }
            }
        } else {
            $this->error(
                'Drupal core needs to be installed prior to running this command. Please look into using `composer create-project pr0ject-x/drupal-recommended-project <project-dir>` to get up quickly with Drupal 8.'
            );
        }
    }

    /**
     * Setup the local Drupal application.
     */
    public function drupalLocalSetup(): void
    {
        if (
            PxApp::composerHasPackage('drupal/core')
            || PxApp::composerHasPackage('drupal/core-recommended')
        ) {
            $this->writeDrupalSettings(
                '/^if.+\(file_exists\(.+settings.local.php\'\)\)\s*?{\n.+\n}$/m',
                Drupal::loadSettingSnippet('settings.local.txt')
            );
            $drupalLocalSettings = $this->drupalProjectSettingsPath(true);

            if (!file_exists($drupalLocalSettings)) {
                $this->taskFilesystemStack()
                    ->copy(
                        "{$this->drupalProjectRootPath()}/sites/example.settings.local.php",
                        $drupalLocalSettings
                    )->run();
            }

            $drupalLocalTask = $this->taskWriteToFile($drupalLocalSettings)
                ->append()
                ->appendUnlessMatches(
                    '/^\$databases\[.+]\s+?=\s+?(\[|array\()$/m',
                    Drupal::loadSettingSnippet('settings.database.txt')
                );

            foreach ($this->drupalProjectDatabase()->databaseInfo() as $property => $value) {
                $drupalLocalTask->place($property, $value);
            }
            $result = $drupalLocalTask->run();

            if ($result->wasSuccessful()) {
                $this->success('The Drupal local setup was successfully completed.');
            }
        } else {
            $this->error(
                'Drupal core needs to be installed prior to running this command. Please look into using `composer create-project pr0ject-x/drupal-recommended-project <project-dir>` to get up quickly with Drupal 8.'
            );
        }
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
     * Retrieve the Drupal project root path.
     *
     * @return string
     */
    protected function drupalProjectRootPath(): string
    {
        return $this->getPlugin()->drupalProjectRootPath();
    }

    /**
     * Retrieve the Drupal database connection information.
     *
     * @return \Pr0jectX\PxDrupal\DrupalDatabase
     */
    protected function drupalProjectDatabase()
    {
        return $this->getPlugin()->drupalProjectDatabase();
    }

    /**
     * The full project path to the Drupal settings.
     *
     * @param bool $isLocal
     *   Determine if the Drupal local setting path is provided.
     *
     * @return string
     *   The full project path to the Drupal settings.
     */
    protected function drupalProjectSettingsPath(bool $isLocal = false): string
    {
        return $this->getPlugin()->drupalProjectSettingPath($isLocal);
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
     * Get the Drupal command type plugin.
     *
     * @return \Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\DrupalCommandType
     */
    protected function getPlugin(): DrupalCommandType
    {
        return $this->plugin;
    }

    /**
     * Generate the Drupal salt hash.
     *
     * @param int $bytes
     *   A number to use for the random bytes.
     *
     * @return string
     *   The generate salt has key.
     *
     * @throws \Exception
     */
    protected function drupalGenerateSaltHash(int $bytes = 55): string
    {
        return str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode(random_bytes($bytes))
        );
    }

    /**
     * Check if the Drupal module exist.
     *
     * @param string $module
     *   The Drupal machine name.
     * @param string $drupalVersion
     *   The Drupal version (7.x, 8.x, 9.x)
     *
     * @return bool
     *   Return true if the Drupal module exist; otherwise false.
     */
    protected function drupalModuleExist(string $module, string $drupalVersion): bool
    {
        if ($drupalReleaseXml = file_get_contents(self::DRUPAL_UPDATE_URL . "/{$module}/{$drupalVersion}")) {
            return (new \SimpleXMLElement($drupalReleaseXml))->project_status == 'published';
        }

        return false;
    }
}
