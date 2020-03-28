<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType;

use Pr0jectX\Px\ConfigTreeBuilder\ConfigTreeBuilder;
use Pr0jectX\Px\ProjectX\Plugin\PluginCommandRegisterInterface;
use Pr0jectX\Px\ProjectX\Plugin\PluginConfigurationBuilderInterface;
use Pr0jectX\Px\ProjectX\Plugin\PluginTasksBase;
use Pr0jectX\Px\PxApp;
use Pr0jectX\PxDrupal\CommandProviders\DrupalDrushProvider;
use Pr0jectX\PxDrupal\CommandProviders\DrupalProviderInterface;
use Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\Commands\DrupalCommands;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Define the Drupal command type.
 */
class DrupalCommandType extends PluginTasksBase implements PluginConfigurationBuilderInterface, PluginCommandRegisterInterface
{
    /** @var string  */
    const DEFAULT_COMMAND_PROVIDER = 'drush';

    /**
     * @inheritDoc
     */
    public static function pluginId(): string
    {
        return 'drupal';
    }

    /**
     * @inheritDoc
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
     * {@inheritDoc}
     */
    public function pluginConfiguration(): ConfigTreeBuilder
    {
        return (new ConfigTreeBuilder())
            ->setQuestionInput($this->input)
            ->setQuestionOutput($this->output)
            ->createNode('provider')
                ->setValue(new ChoiceQuestion(
                    $this->formatQuestionDefault('Select the Drupal command provider', $this->drupalCommandProviderType()),
                    $this->getCommandProviderOptions(),
                    $this->drupalCommandProviderType()
                ))
            ->end();
    }

    /**
     * Drupal command provider instance.
     *
     * @return \Pr0jectX\PxDrupal\CommandProviders\DrupalProviderInterface
     *   The Drupal command provider instance.
     */
    public function drupalProviderInstance(): DrupalProviderInterface
    {
        return $this->createProviderInstance(
            $this->drupalCommandProviderType()
        );
    }

    /**
     * Define Drupal command provider type.
     *
     * @return string
     *   The drupal command provider type.
     */
    public function drupalCommandProviderType(): string
    {
        return $this->getConfigurations()['provider'] ?? static::DEFAULT_COMMAND_PROVIDER;
    }

    /**
     * Create the Drupal command provider instance.
     *
     * @param string $type
     *   The Drupal command provider type.
     *
     * @return \Pr0jectX\PxDrupal\CommandProviders\DrupalProviderInterface
     *   The Drupal command provider instance.
     */
    protected function createProviderInstance(string $type): DrupalProviderInterface
    {
        $providers = $this->drupalCommandProviders();

        if (!isset($providers[$type])) {
            throw new \RuntimeException(
                sprintf('The Drupal command provider (%s) is invalid.', $type)
            );
        }
        $providerInstance = $providers[$type];

        if (!is_subclass_of($providerInstance, DrupalProviderInterface::class)) {
            throw new \RuntimeException(
                'The Drupal command provider class is invalid!'
            );
        }

        if (!in_array($type, PxApp::getEnvironmentInstance()->envPackages())) {
            throw new \RuntimeException(
                sprintf("The Drupal environment doesn't support %s!", $type)
            );
        }

        return new $providerInstance($this->drupalEnvironmentRoot());
    }

    /**
     * Define Drupal command providers.
     *
     * @return array
     *   An array of Drupal command providers.
     */
    protected function drupalCommandProviders(): array
    {
        return [
            'drush' => DrupalDrushProvider::class,
        ];
    }

    /**
     * Get Drupal command provider options.
     *
     * @return array
     *   An array of drupal command provider options.
     */
    protected function getCommandProviderOptions(): array
    {
        return array_keys($this->drupalCommandProviders());
    }

    /**
     * Retrieve the Drupal environment root path.
     *
     * @return string
     *   The Drupal environment root path.
     */
    protected function drupalEnvironmentRoot(): string
    {
        return PxApp::getEnvironmentInstance()->envAppRoot();
    }
}
