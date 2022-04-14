<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\Commands;

use Pr0jectX\Px\ProjectX\Plugin\PluginCommandTaskBase;
use Pr0jectX\Px\PxApp;
use Pr0jectX\PxDrupal\Drupal;
use Pr0jectX\PxDrupal\DrupalCommandResolver;
use Pr0jectX\PxDrupal\DrupalDatabase;
use Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\DrupalCommandTrait;
use Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\DrupalCommandType;
use Robo\Result;

/**
 * Define the Drupal commands.
 */
class DrupalCommands extends PluginCommandTaskBase
{
    use DrupalCommandTrait;

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
            $this
                ->createDrupalSettings()
                ->createDrupalServices();

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
                [$this->drupalProjectDatabase(true)->databaseUrl(), $profile, $options]
            );
            $result = reset($results);

            if ($result->wasSuccessful()) {
                $this->success('Drupal was successfully installed!');

                if (PxApp::getEnvironmentInstance()->isRunning()) {
                    if ($this->confirm('Do you want to launch the Drupal application?', true)) {
                        $this->taskSymfonyCommand($this->findCommand('env:launch'))->run();
                    }
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

            foreach ($this->drupalProjectDatabase(true)->databaseInfo() as $property => $value) {
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
     * Install a new Drupal module using composer and enable.
     *
     * @param array $module
     *   One or list of Drupal modules.
     *
     * @param array $opts
     * @option $yes Install modules without confirmation.
     *
     * @aliases drupal:mi
     */
    public function drupalModuleInstall(array $module, array $opts = [
        'yes' => false,
    ])
    {
        if (Drupal::moduleExist($module, '8.x')) {
            $result = $this->installComposerPackages($module, 'drupal');

            if ($result->wasSuccessful()) {
                $options = $this->processCommandOptions($opts);

                $this->runDrupalCommand(
                    'moduleInstall',
                    [$module, $options]
                );
            }
        } else {
            throw new \InvalidArgumentException(sprintf(
                "One or more Drupal modules %s are invalid. Please refer to Drupal.org.",
                implode(', ', $module)
            ));
        }
    }

    /**
     * Remove a Drupal module using composer and uninstall.
     *
     * @param array $module
     *   One or list of Drupal modules.
     *
     * @param array $opts
     * @option $yes Remove modules without confirmation.
     *
     * @aliases drupal:mr
     */
    public function drupalModuleRemove(array $module, array $opts = [
        'yes' => false,
    ])
    {
        if (Drupal::moduleExist($module, '8.x')) {
            $options = $this->processCommandOptions($opts);
            $results = $this->runDrupalCommand(
                'moduleRemove',
                [$module, $options]
            );

            /** @var \Robo\Result $result */
            if ($result = reset($results)) {
                if (!$result->wasSuccessful()) {
                    return;
                }
                $this->removeComposerPackages($module, 'drupal');
            }
        } else {
            throw new \InvalidArgumentException(sprintf(
                "One or more Drupal modules %s are invalid. Please refer to Drupal.org.",
                implode(', ', $module)
            ));
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
     * @param bool $internal
     *   Set true if database is internal.
     *
     * @return \Pr0jectX\PxDrupal\DrupalDatabase
     */
    protected function drupalProjectDatabase(bool $internal = false): DrupalDatabase
    {
        return $this->getPlugin()->drupalProjectDatabase($internal);
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
     * Create the Drupal settings file.
     *
     * @return \Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\Commands\DrupalCommands
     */
    protected function createDrupalSettings(): DrupalCommands
    {
        $settingsFilePath = $this->drupalProjectSettingsPath();

        if (!file_exists($settingsFilePath)) {
            $this->taskWriteToFile($settingsFilePath)
                ->text(Drupal::loadSettingBase())
                ->run();
        }

        return $this;
    }

    /**
     * Install the composer packages.
     *
     * @param array $packages
     *   An array of PHP packages.
     * @param string $defaultVendor
     *   The default vendor if not defined by the package.
     *
     * @return \Robo\Result
     *   The command result instance.
     */
    protected function installComposerPackages(
        array $packages,
        string $defaultVendor
    ): Result {
        return $this->taskComposerRequire()
            ->args($this->formatComposerPackages($packages, $defaultVendor))
            ->run();
    }

    /**
     * Remove the composer packages.
     *
     * @param array $packages
     *   An array of PHP packages.
     * @param string $defaultVendor
     *   The default vendor if not defined by the package.
     *
     * @return \Robo\Result
     *   The command result instance.
     */
    protected function removeComposerPackages(
        array $packages,
        string $defaultVendor
    ): Result {
        $packages = $this->formatComposerPackages($packages, $defaultVendor);

        return $this->taskComposerRemove()
            ->args(array_filter($packages, function ($package) {
                // Filter out composer packages that don't exist.
                return PxApp::composerHasPackage($package);
            }))
            ->run();
    }

    /**
     * Format the composer packages.
     *
     * @param array $packages
     *   An array of PHP packages.
     * @param string $defaultVendor
     *   The default vendor if not defined by the package.
     *
     * @return array
     *   An array of formatted composer packages.
     */
    protected function formatComposerPackages(
        array $packages,
        string $defaultVendor
    ): array {
        foreach ($packages as &$package) {
            $packageName = $package['name']
                ?? $package;
            $packageVendor = $package['vendor']
                ?? $defaultVendor;
            $packageVersion = $package['version']
                ?? null;

            $package = !isset($packageVersion)
                ? "{$packageVendor}/{$packageName}"
                : "{$packageVendor}/{$packageName}:{$packageVersion}";
        }

        return $packages;
    }

    /**
     * Process command options.
     *
     * @param array $options
     *   An array of options to process.
     *
     * @return array
     *   The processed command options.
     */
    protected function processCommandOptions(array $options): array
    {
        $commandOptions = [];

        foreach ($options as $key => $value) {
            if (is_bool($value) && false === $value) {
                continue;
            }

            $commandOptions[$key] = is_bool($value) && true === $value
                ? null
                : $value;
        }

        return $commandOptions;
    }

    /**
     * Create the Drupal services file.
     *
     * @return \Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\Commands\DrupalCommands
     */
    protected function createDrupalServices(): DrupalCommands
    {
        $serviceFilePath = "{$this->drupalProjectRootPath()}/sites/default/services.yml";

        if (!file_exists($serviceFilePath)) {
            $this->taskFilesystemStack()
                ->copy(
                    "{$this->drupalProjectRootPath()}/sites/default/default.services.yml",
                    $serviceFilePath
                )->run();
        }

        return $this;
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

            $commandResolver = new DrupalCommandResolver(
                $provider,
                PxApp::getEnvironmentInstance()->execBuilderOptions()
            );

            if ($commands = (array) $commandResolver->exec($method, $args)) {
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
}
