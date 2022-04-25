<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType;

use Pr0jectX\Px\ProjectX\Plugin\EnvironmentType\EnvironmentTypeInterface;
use Pr0jectX\Px\ProjectX\Plugin\PluginCommandRegisterInterface;
use Pr0jectX\Px\ProjectX\Plugin\PluginTasksBase;
use Pr0jectX\Px\PxApp;
use Pr0jectX\PxDrupal\DrupalCommandProviderManager;
use Pr0jectX\PxDrupal\DrupalDatabase;
use Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\Commands\DrupalCommands;

/**
 * Define the Drupal command type.
 */
class DrupalCommandType extends PluginTasksBase implements PluginCommandRegisterInterface
{
    /**
     * {@inheritDoc}
     */
    public static function pluginId(): string
    {
        return 'drupal';
    }

    /**
     * {@inheritDoc}
     */
    public static function pluginLabel(): string
    {
        return 'Drupal';
    }

    /**
     * {@inheritDoc}
     */
    public function registeredCommands(): array
    {
        return [
            DrupalCommands::class
        ];
    }

    /**
     * Retrieve the full path to the Drupal project.
     *
     * @return string
     *   The Drupal project full path.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function drupalProjectRootPath(): ?string
    {
        $dirs = ['web', 'docroot'];
        $projectRoot = PxApp::projectRootPath();

        foreach ($dirs as $dir) {
            $path = "$projectRoot/$dir";
            if (file_exists($path)) {
                return $path;
            }
        }
        $envRoot = $this->getEnvironmentInstance()->envAppRoot();
        $envDir = dirname($envRoot);

        if (file_exists("$projectRoot/$envDir")) {
            return "$projectRoot/$envDir";
        }

        return null;
    }

    /**
     * Get Drupal command provider manager.
     *
     * @return \Pr0jectX\PxDrupal\DrupalCommandProviderManager
     *   The Drupal command provider manager.
     */
    public function drupalCommandProviderManager(): DrupalCommandProviderManager
    {
        return new DrupalCommandProviderManager();
    }

    /**
     * Retrieve the Drupal project database information.
     *
     * @param bool $internal
     *   Set true if the database is internal.
     *
     * @return \Pr0jectX\PxDrupal\DrupalDatabase
     *   The Drupal database instance.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function drupalProjectDatabase(bool $internal = false): DrupalDatabase
    {
        return new DrupalDatabase($this->getEnvironmentInstance()->selectEnvDatabase(
            EnvironmentTypeInterface::ENVIRONMENT_DB_PRIMARY,
            $internal
        ));
    }

    /**
     * Retrieve the Drupal settings path.
     *
     * @param bool $isLocal
     *   Set true to retrieve the local Drupal settings path.
     *
     * @return string
     *   The full path to the Drupal settings.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function drupalProjectSettingPath(bool $isLocal = false): string
    {
        if (!$isLocal) {
            return "{$this->drupalProjectRootPath()}/sites/default/settings.php";
        }

        return "{$this->drupalProjectRootPath()}/sites/default/settings.local.php";
    }

    /**
     * Get the project environment instance.
     *
     * @return \Pr0jectX\Px\ProjectX\Plugin\EnvironmentType\EnvironmentTypeInterface
     *   The project environment instance.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function getEnvironmentInstance(): EnvironmentTypeInterface
    {
        return PxApp::getEnvironmentInstance();
    }
}
