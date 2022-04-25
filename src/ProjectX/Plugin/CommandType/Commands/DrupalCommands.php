<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\Commands;

use Pr0jectX\Px\Datastore\JsonDatastore;
use Pr0jectX\Px\ProjectX\Plugin\PluginCommandTaskBase;
use Pr0jectX\Px\PxApp;
use Pr0jectX\PxDrupal\CommandProviders\DrupalCommandProviderInterface;
use Pr0jectX\PxDrupal\Drupal;
use Pr0jectX\PxDrupal\DrupalCommandResolver;
use Pr0jectX\PxDrupal\DrupalDatabase;
use Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\DrupalCommandTrait;
use Robo\Result;
use Symfony\Component\Console\Question\Question;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Define the Drupal commands.
 */
class DrupalCommands extends PluginCommandTaskBase
{
    use DrupalCommandTrait;

    /**
     * Execute arbitrary command for Drupal console, or Drush.
     *
     * @param array $cmd
     *   The arbitrary command.
     * @param array $opts
     *   The command options.
     *
     * @option $provider
     *   The Drupal command provider type (e.g. drush).
     *
     * @aliases drupal, drush
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function drupalExec(array $cmd, array $opts = [
        'provider' => 'drush'
    ]): void
    {
        try {
            $this->runDrupalCommand(
                'exec',
                [trim(implode(' ', $cmd))],
                $opts
            );
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }



    /**
     * Install a fresh Drupal site locally.
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
     * @option $provider
     *   The Drupal command provider type.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function drupalInstall(string $profile = 'standard', array $opts = [
        'site-name' => 'Drupal Project',
        'site-mail' => 'admin@example.com',
        'account-name' => 'admin',
        'account-pass' => 'admin',
        'account-mail' => 'admin@example.com',
        'provider' => 'drush'
    ]): void
    {
        print Drupal::pluginBanner();

        try {
            $this->checkDrupalInstallation();

            $this->createDrupalSettings();
            $this->createDrupalServices();

            $this->askDrupalSetupSaltHash();
            $this->askDrupalSetupConfiguration();
            $this->askDrupalDisableModuleUpdate();

            $this->taskSymfonyCommand($this->findCommand('drupal:setup'))
                ->arg('environment', 'local')
                ->run();

            $options = $this->buildDrupalInstallOptions();
            $results = $this->runDrupalCommand(
                'install',
                [$this->drupalProjectDatabase(true)->databaseUrl(), $profile, $options],
                $opts
            );
            $result = reset($results);

            if ($result->wasSuccessful()) {
                $this->success('Drupal was successfully installed!');
                if (
                    PxApp::getEnvironmentInstance()->isRunning()
                    && $this->confirm('Do you want to launch the Drupal application?', true)
                ) {
                    $this->taskSymfonyCommand($this->findCommand('env:launch'))->run();
                }
            }
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Set up a Drupal project for an environment.
     *
     * @param string $environment
     *   The environment to setup (e.g. local).
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function drupalSetup(string $environment = 'local'): void
    {
        try {
            $this->checkDrupalInstallation();

            if ($environment === 'local') {
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
                    $this->success('The Drupal project was successfully setup locally.');
                }
            }
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Patch a Drupal module package.
     *
     * @return void
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function drupalPatch(): void
    {
        try {
            do {
                $issueNumber = (int) $this->doAsk((new Question(
                    $this->formatQuestionDefault('Input the Drupal issue URL or ID')
                ))->setNormalizer(static function ($value) {
                    if (isset($value)) {
                        $value = trim($value);

                        $matches = [];
                        $urlPattern = '/^https?:\/\/(?:www.)?drupal.org\/project\/drupal\/issues\/(\d+)$/';

                        if (preg_match($urlPattern, $value, $matches)) {
                            return $matches[1];
                        }
                    }

                    return $value;
                })
                ->setValidator(static function ($value) {
                    if (!isset($value) || !is_numeric($value)) {
                        throw new \RuntimeException(
                            'The Drupal issue number is required!'
                        );
                    }

                    return $value;
                }));
                $this->drupalPatchPackage(
                    $this->fetchDrupalIssueInfo($issueNumber)
                );
            } while ($this->confirm('Patch another Drupal package?'));

            $this->taskComposerUpdate()->option('lock')->run();
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Login to the Drupal application.
     *
     * @param array $opts
     * @aliases drupal:letmein
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function drupalLogin(array $opts = [
        'provider' => 'drush'
    ]): void
    {
        $this->runDrupalCommand('login', [], $opts);
    }

    /**
     * Rebuild the Drupal cache.
     *
     * @aliases drupal:cr, drupal:cc
     */
    public function drupalCacheRebuild(array $opts = [
        'provider' => 'drush'
    ]): void
    {
        $this->runDrupalCommand('cacheRebuild', [], $opts);
    }

    /**
     * Install or uninstall one or more Drupal modules.
     *
     * @param string $action
     *   The action to execute (install or uninstall).
     * @param array $module
     *   One or more Drupal modules.
     *
     * @param array $opts
     * @option $yes Install modules without confirmation.
     *
     * @aliases drupal:mod
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function drupalModule(string $action, array $module, array $opts = [
        'yes' => false,
        'provider' => 'drush'
    ]): void
    {
        try {
            if (empty($module)) {
                throw new \InvalidArgumentException(
                    'You need to define one or Drupal module(s)!'
                );
            }

            $action = $this->normalizeModuleAction($action) ?? $this->askChoice(
                'Select the action to execute',
                $this->moduleActions(),
                'install'
            );

            if ($action === 'install') {
                $this->drupalModuleInstall($module, $opts);
            }

            if ($action === 'uninstall') {
                $this->drupalModuleUninstall($module, $opts);
            }
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Create local Drupal user account.
     *
     * @param string $username
     *   The account username.
     *
     * @param array $opts
     * @option $role
     *   The account user role name
     * @option $email
     *   The account user email address.
     * @option $password
     *   The account user password.
     *
     * @aliases drupal:ca
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function drupalCreateAccount(
        string $username = 'dev',
        array $opts = [
            'role' => 'administrator',
            'email' => 'dev@example.com',
            'password' => 'dev',
            'provider' => 'drush'
        ]
    ): void {
        $this->runDrupalCommand(
            'createAccount',
            [$username, $opts],
            $opts
        );
    }

    /**
     * Check Drupal installation.
     *
     * @return void
     */
    protected function checkDrupalInstallation(): void
    {
        if (
            !PxApp::composerHasPackage('drupal/core')
            && !PxApp::composerHasPackage('drupal/core-recommended')
        ) {
            throw new \RuntimeException(
                'Drupal core needs to be installed prior to running this command. Please look into using
                    `composer create-project pr0ject-x/drupal-recommended-project <project-dir>` to get up quickly with
                    Drupal.'
            );
        }
    }

    /**
     * Composer Drupal packages.
     *
     * @return array
     *   An array of Drupal composer packages.
     */
    protected function composerDrupalPackages(): array
    {
        $composer = PxApp::getProjectComposer();

        $packages = array_filter(array_keys($composer['require']), static function ($package) {
            return strpos($package, 'drupal/') !== false;
        });

        if (in_array('drupal/core-recommended', $packages, true)) {
            $packages[] = 'drupal/core';
        }

        return array_values($packages);
    }

    /**
     * Retrieve the Drupal project root path.
     *
     * @return string
     *   The Drupal project root path.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function drupalProjectRootPath(): string
    {
        return $this->getPlugin()->drupalProjectRootPath();
    }

    /**
     * Retrieve the Drupal database connection.
     *
     * @param bool $internal
     *   Set true if database is internal.
     *
     * @return \Pr0jectX\PxDrupal\DrupalDatabase
     *   The Drupal database instance.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
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
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function drupalProjectSettingsPath(bool $isLocal = false): string
    {
        return $this->getPlugin()->drupalProjectSettingPath($isLocal);
    }

    /**
     * Create the Drupal settings file.
     *
     * @return self
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function createDrupalSettings(): self
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
     * Build Drupal installation options.
     *
     * @return array
     *   An array of custom installation options.
     */
    protected function buildDrupalInstallOptions(): array
    {
        $options = [];
        $installOptions = Drupal::defaultInstallOptions();

        foreach (array_keys($installOptions) as $property) {
            $label = str_replace('-', ' ', $property);
            $options[$property] = $this->askDefault(
                sprintf('Input the %s', $label),
                $options[$property] ?? $installOptions[$property]
            );
        }

        return array_intersect_key($options, $installOptions);
    }

    /**
     * Drupal module install.
     *
     * @param array|string $module
     *   One or more Drupal modules.
     * @param array $opts
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function drupalModuleInstall($module, array $opts = []): void
    {
        $options = $this->processCommandOptions($opts);
        $result = $this->installComposerPackages($module, 'drupal');
        if ($result->wasSuccessful()) {
            $this->runDrupalCommand(
                'moduleInstall',
                [$module, $options],
                $opts
            );
        }
    }

    /**
     * Drupal module uninstall.
     *
     * @param array|string $module
     *   One or more Drupal modules.
     * @param array $opts
     *
     * @return void
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function drupalModuleUninstall($module, array $opts): void
    {
        $options = $this->processCommandOptions($opts);
        $results = $this->runDrupalCommand(
            'moduleRemove',
            [$module, $options],
            $opts
        );

        if (($result = reset($results)) && $result->wasSuccessful()) {
            $this->removeComposerPackages($module, 'drupal');
        }
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
            ->args(array_filter($packages, static function ($package) {
                // Filter out composer packages that don't exist.
                return PxApp::composerHasPackage($package);
            }))
            ->run();
    }

    /**
     * Define the module actions.
     *
     * @return array
     */
    protected function moduleActions(): array
    {
        return [
            'install' => [
                'in'
            ],
            'uninstall' => [
                'un'
            ]
        ];
    }

    /**
     * Normalize the module action.
     *
     * @param string $action
     *   The module action, or shortcut.
     *
     * @return string
     *   The normalized module action.
     */
    protected function normalizeModuleAction(
        string $action
    ): ?string {
        $actions = $this->moduleActions();

        if (array_key_exists($action, $actions)) {
            return $action;
        }
        $shortcut = substr($action, 0, 2);

        foreach ($actions as $key => $shortcuts) {
            if (in_array($shortcut, $shortcuts, true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Drupal patch package.
     *
     * @param array $issueInfo
     *   An array of Drupal issue information.
     *
     * @return void
     */
    protected function drupalPatchPackage(array $issueInfo): void
    {
        if (isset($issueInfo['title'], $issueInfo['issue'], $issueInfo['patches'])) {
            $issueTitle = $issueInfo['title'];
            $issueNumber = $issueInfo['issue'];
            $issuePatches = $issueInfo['patches'];

            $composer = PxApp::getProjectComposer();

            $issuePackage = $this->askChoice(
                'Select Drupal package',
                $this->composerDrupalPackages()
            );
            $issuePatchName = $this->askChoice(
                'Select the issue patch',
                array_keys($issuePatches),
                '0'
            );
            $issuePatchUrl = $issuePatches[$issuePatchName];

            $patches[$issuePackage] = ["#$issueNumber: $issueTitle" => $issuePatchUrl];

            if (isset($composer['extra']['patches-file'])) {
                $patchesFile = $composer['extra']['patches-file'];
                $patchesFilePath = PxApp::projectRootPath() . "/$patchesFile";
                (new JsonDatastore($patchesFilePath))->merge()->write(
                    ['patches' => $patches]
                );
            } else {
                foreach ($patches as $vendor => $patch) {
                    if (empty($patch) || !is_array($patch)) {
                        continue;
                    }
                    $this->taskComposerConfig()
                        ->option('json')
                        ->option('merge')
                        ->rawArg("extra.patches.$vendor")
                        ->arg(json_encode($patch, JSON_UNESCAPED_SLASHES))
                        ->run();
                }
            }
        }
    }

    /**
     * Fetch Drupal issue files.
     *
     * @param array $files
     *   An array of Drupal issue files.
     *
     * @return array
     *   An array of Drupal issue patches.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function fetchDrupalIssueFiles(array $files, int $limit = 10): array
    {
        $patches = [];

        foreach (array_slice(array_reverse($files), 0, $limit) as $file) {
            if (!isset($file['file']['uri'])) {
                continue;
            }
            $response = $this->httpClient()->request(
                'GET',
                "{$file['file']['uri']}.json"
            );

            if ($response->getStatusCode() === 200) {
                $content = $response->toArray();
                if (
                    !isset($content['name'], $content['url'])
                    || pathinfo($content['name'], PATHINFO_EXTENSION) !== 'patch'
                ) {
                    continue;
                }
                $patches[$content['name']] = $content['url'];
            }
        }

        return $patches;
    }

    /**
     * Fetch the Drupal issue information.
     *
     * @param int $issueNumber
     *   The Drupal issue number.
     *
     * @return array
     *   An array of Drupal issue information.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function fetchDrupalIssueInfo(int $issueNumber): array
    {
        $apiEndpoint = 'https://www.drupal.org/api-d7';
        $response = $this->httpClient()->request(
            'GET',
            "$apiEndpoint/node.json?type=project_issue&nid=$issueNumber"
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf(
                'Unable to fetch the Drupal issue for %d!',
                $issueNumber
            ));
        }
        $data = $response->toArray();
        $content = reset($data['list']);

        if (!isset($content['field_issue_files'])) {
            throw new \RuntimeException(sprintf(
                'Drupal issue %d does not contain any patches!',
                $issueNumber
            ));
        }

        return [
            'issue' => $issueNumber,
            'title' => $content['title'],
            'patches' => $this->fetchDrupalIssueFiles(
                $content['field_issue_files']
            )
        ];
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
                ? "$packageVendor/$packageName"
                : "$packageVendor/$packageName:$packageVersion";
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
            if (
                $key === 'provider'
                || (is_bool($value) && false === $value)
            ) {
                continue;
            }

            $commandOptions[$key] = true === $value
                ? null
                : $value;
        }

        return $commandOptions;
    }

    /**
     * Create the Drupal services file.
     *
     * @return self
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function createDrupalServices(): self
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
     * @param array $options
     * @return array
     *   Return true if the Drupal command ran; otherwise false.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function runDrupalCommand(
        string $method,
        array $args = [],
        array $options = []
    ): array {
        $results = [];

        if ($envExecCommand = $this->findCommand('env:execute')) {
            $provider = $this->createDrupalCommandProviderFromOptions($options);

            $commandResolver = new DrupalCommandResolver(
                $provider,
                PxApp::getEnvironmentInstance()->execBuilderOptions()
            );

            if ($commands = $commandResolver->exec($method, $args)) {
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
     * Create the Drupal command provider from options.
     *
     * @param array $options
     *   An array of options.
     *
     * @return \Pr0jectX\PxDrupal\CommandProviders\DrupalCommandProviderInterface
     *   The Drupal command provider instance.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function createDrupalCommandProviderFromOptions(
        array $options
    ): DrupalCommandProviderInterface {
        $providerType = $options['provider'] ?? 'drush';
        $providerManager = $this->getPlugin()->drupalCommandProviderManager();

        if (!$providerManager->hasCommandProvider($providerType)) {
            throw new \InvalidArgumentException(sprintf(
                'The Drupal command provider %s is invalid!',
                $providerType
            ));
        }

        return $providerManager->createInstance($providerType);
    }

    /**
     * The HTTP client.
     *
     * @return \Symfony\Contracts\HttpClient\HttpClientInterface
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function httpClient(): HttpClientInterface
    {
        return PxApp::service('httpClient');
    }
}
