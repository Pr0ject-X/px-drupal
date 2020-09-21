<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\CommandProviders;

use Pr0jectX\Px\ExecutableBuilder\ExecutableBuilderBase;
use Pr0jectX\PxDrupal\Drupal;
use Pr0jectX\PxDrupal\ExecutableBuilder\Commands\Drush;

/**
 * Define the Drush command provider.
 */
class DrupalDrushProvider implements DrupalProviderInterface
{
    /**
     * @var string
     */
    protected $root;

    /**
     * The Drupal drush command provider.
     *
     * @param string $root
     *   The Drush working root directory.
     */
    public function __construct(string $root)
    {
        $this->root = $root;
    }

    /**
     * {@inheritDoc}
     */
    public function cacheRebuild(): ExecutableBuilderBase
    {
        return $this->exec('cr');
    }

    /**
     * {@inheritDoc}
     */
    public function moduleInstall($module, array $options = []): array
    {
        $arguments = !is_array($module)
            ? [$module]
            : $module;

        return $this->execCollection([
            'en' => [
                'options' => $options,
                'arguments' => $arguments,
            ],
            'cr' => []
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function moduleRemove($module, array $options = []): array
    {
        $arguments = !is_array($module)
            ? [$module]
            : $module;

        return $this->execCollection([
            'pmu' => [
                'options' => $options,
                'arguments' => $arguments
            ],
            'cr' => []
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function createAccount(string $username, array $options = []): array
    {
        return $this->execCollection([
            'ucrt' => [
                'arguments' => [$username],
                'options' => [
                    'mail' => $options['email'],
                    'password' => $options['password'],
                ]
            ],
            'urol' => [
                'arguments' => [$options['role']],
                'options' => [
                    'name' => $username,
                ]
            ]
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function login(): ExecutableBuilderBase
    {
        return $this->exec('uli');
    }

    /**
     * {@inheritDoc}
     */
    public function install(
        string $dbUrl,
        string $profile = 'standard',
        array $options = []
    ): ExecutableBuilderBase {
        $options += [
            'db-url' => $dbUrl,
        ] + Drupal::defaultInstallOptions();

        return $this->exec('site-install')->setArgument($profile)->setOptions($options);
    }

    /**
     * {@inheritDoc}
     */
    public function exec($command = null, array $options = []): ExecutableBuilderBase
    {
        $drush = $this->drushInstance();

        if (isset($command)) {
            $drush->setArgument((string) $command);
        }

        return $drush;
    }

    /**
     * Execute collection commands.
     *
     * @param array $commands
     *   An array of commands.
     *
     * @return array
     *   An array of executable.
     */
    protected function execCollection(array $commands): array
    {
        $collection = [];

        foreach ($commands as $command => $info) {
            $options = $info['options'] ?? [];
            $arguments = $info['arguments'] ?? [];

            $collection[] = ($this->exec($command))
                ->setOptions($options)
                ->setArguments($arguments);
        }

        return $collection;
    }

    /**
     * Get the Drush instance.
     *
     * @return \Pr0jectX\Px\ExecutableBuilder\ExecutableBuilderBase
     */
    protected function drushInstance(): ExecutableBuilderBase
    {
        return (new Drush())->setOption('root', $this->root);
    }
}
