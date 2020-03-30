<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType;

use Pr0jectX\Px\ConfigTreeBuilder\ConfigTreeBuilder;
use Pr0jectX\Px\PluginManagerInterface;
use Pr0jectX\Px\ProjectX\Plugin\EnvironmentType\EnvironmentDatabase;
use Pr0jectX\Px\ProjectX\Plugin\EnvironmentType\EnvironmentTypeInterface;
use Pr0jectX\Px\ProjectX\Plugin\PluginCommandRegisterInterface;
use Pr0jectX\Px\ProjectX\Plugin\PluginConfigurationBuilderInterface;
use Pr0jectX\Px\ProjectX\Plugin\PluginTasksBase;
use Pr0jectX\Px\PxApp;
use Pr0jectX\PxDrupal\CommandProviders\DrupalDrushProvider;
use Pr0jectX\PxDrupal\CommandProviders\DrupalProviderInterface;
use Pr0jectX\PxDrupal\DrupalDatabase;
use Pr0jectX\PxDrupal\ProjectX\Plugin\CommandType\Commands\DrupalCommands;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Define the Drupal command type.
 */
class DrupalCommandType extends PluginTasksBase implements
    PluginConfigurationBuilderInterface,
    PluginCommandRegisterInterface
{
    /**
     * @var string
     */
    protected const DEFAULT_DRUPAL_ROOT = 'web';

    /**
     * @var string
     */
    protected const DEFAULT_COMMAND_PROVIDER = 'drush';

    /**
     * @var string
     */
    protected $projectRoot;

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
    public function __construct(
        PluginManagerInterface $plugin_manager,
        array $configurations
    ) {
        parent::__construct($plugin_manager, $configurations);
        $this->projectRoot = PxApp::projectRootPath();
    }

    /**
     * {@inheritDoc}
     */
    public function pluginConfiguration(): ConfigTreeBuilder
    {
        return (new ConfigTreeBuilder())
            ->setQuestionInput($this->input)
            ->setQuestionOutput($this->output)
            ->createNode('drupal_root')
                ->setValue(new Question(
                    $this->formatQuestionDefault('Input the Drupal root', static::DEFAULT_DRUPAL_ROOT),
                    static::DEFAULT_DRUPAL_ROOT
                ))
            ->end()
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
     * Retrieve the Drupal project database information.
     *
     * @return \Pr0jectX\PxDrupal\DrupalDatabase
     */
    public function drupalProjectDatabase(): DrupalDatabase
    {
        return new DrupalDatabase(
            $this->getEnvPrimaryDatabase()
        );
    }

    /**
     * Retrieve the full path to the Drupal project.
     *
     * @return string
     *   The full path to the Drupal project.
     */
    public function drupalProjectRootPath(): string
    {
        return "{$this->projectRoot}/{$this->drupalRoot()}";
    }

    /**
     * Retrieve the full project path to the Drupal settings.
     *
     * @param bool $isLocal
     *   Set true to retrieve the local Drupal settings path.
     *
     * @return string
     *   The full project path to the Drupal settings.
     */
    public function drupalProjectSettingPath(bool $isLocal = false): string
    {
        if (!$isLocal) {
            return "{$this->drupalProjectRootPath()}/sites/default/settings.php";
        }

        return "{$this->drupalProjectRootPath()}/sites/default/settings.local.php";
    }

    /**
     * Define the Drupal root directory.
     *
     * @return string
     *   The drupal root directory.
     */
    public function drupalRoot(): string
    {
        return $this->getConfigurations()['drupal_root'] ?? static::DEFAULT_DRUPAL_ROOT;
    }

    /**
     * Define the Drupal command provider type.
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

        return new $providerInstance($this->getEnvApplicationRoot());
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
     * Get the environment application root.
     *
     * @return string
     *   The environment application root.
     */
    protected function getEnvApplicationRoot(): string
    {
        return $this->getEnvironmentInstance()->envAppRoot();
    }

    /**
     * Get the environment primary database instance.
     *
     * @return \Pr0jectX\Px\ProjectX\Plugin\EnvironmentType\EnvironmentDatabase
     *   The environment primary database instance.
     */
    protected function getEnvPrimaryDatabase(): EnvironmentDatabase
    {
        return $this->getEnvironmentInstance()->selectEnvDatabase(
            EnvironmentTypeInterface::ENVIRONMENT_DB_PRIMARY
        );
    }

    /**
     * Get the selected environment instance.
     *
     * @return \Pr0jectX\Px\ProjectX\Plugin\EnvironmentType\EnvironmentTypeInterface
     */
    protected function getEnvironmentInstance()
    {
        return PxApp::getEnvironmentInstance();
    }
}
