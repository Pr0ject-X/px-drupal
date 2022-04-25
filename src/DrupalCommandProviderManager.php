<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal;

use Pr0jectX\Px\PxApp;
use Pr0jectX\PxDrupal\CommandProviders\DrupalDrushCommandProvider;
use Pr0jectX\PxDrupal\CommandProviders\DrupalCommandProviderInterface;

/**
 * Define the Drupal command provider manager.
 */
class DrupalCommandProviderManager
{
    /**
     * Has Drupal command provider.
     *
     * @param string $type
     *   The Drupal command provider.
     *
     * @return bool
     */
    public function hasCommandProvider(string $type): bool
    {
        return isset($this->commandProviders()[$type]);
    }

    /**
     * Create the Drupal command provider instance.
     *
     * @param string $type
     *   The Drupal provider type.
     *
     * @return \Pr0jectX\PxDrupal\CommandProviders\DrupalCommandProviderInterface
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function createInstance(string $type): DrupalCommandProviderInterface
    {
        $providers = $this->commandProviders();

        if (!isset($providers[$type])) {
            throw new \RuntimeException(
                sprintf('The Drupal command provider (%s) is invalid.', $type)
            );
        }
        $providerInstance = $providers[$type];

        if (!is_subclass_of($providerInstance, DrupalCommandProviderInterface::class)) {
            throw new \RuntimeException(
                'The Drupal command provider class is invalid!'
            );
        }
        $environmentInstance = PxApp::getEnvironmentInstance();

        if (!in_array($type, $environmentInstance->envPackages(), true)) {
            throw new \RuntimeException(
                sprintf("The Drupal environment doesn't support %s!", $type)
            );
        }

        return new $providerInstance($environmentInstance->envAppRoot());
    }

    /**
     * Define the Drupal command providers.
     *
     * @return string[]
     *   An array of Drupal command providers.
     */
    protected function commandProviders(): array
    {
        return [
            'drush' => DrupalDrushCommandProvider::class,
        ];
    }
}
