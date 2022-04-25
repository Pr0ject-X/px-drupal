<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal;

use Pr0jectX\Px\ExecutableBuilder\ExecutableBuilderBase;
use Pr0jectX\PxDrupal\CommandProviders\DrupalCommandProviderInterface;

/**
 * Drupal command resolver.
 */
class DrupalCommandResolver
{
    /**
     * @var \Pr0jectX\PxDrupal\CommandProviders\DrupalCommandProviderInterface
     */
    protected $provider;

    /**
     * @var array
     */
    protected $execBuilderOptions = [];

    /**
     * Drupal command resolver constructor.
     *
     * @param \Pr0jectX\PxDrupal\CommandProviders\DrupalCommandProviderInterface $provider
     *   The command provider instance.
     * @param array $execBuilderOptions
     *   An array of executable builder options.
     */
    public function __construct(
        DrupalCommandProviderInterface $provider,
        array $execBuilderOptions = []
    ) {
        $this->provider = $provider;
        $this->execBuilderOptions = $execBuilderOptions;
    }

    /**
     * Drupal command resolver method.
     *
     * @param string $method
     *   The command provider executable method.
     * @param array $args
     *   The command provider executable arguments.
     *
     * @return array
     *   The Drupal command string.
     */
    public function exec(string $method, array $args = []): array
    {
        $executables = [];
        $commands = call_user_func_array([$this->provider, $method], $args);

        if (!is_array($commands)) {
            $commands = [$commands];
        }

        foreach ($commands as $executable) {
            if (!$executable instanceof ExecutableBuilderBase) {
                continue;
            }
            $executables[] = $executable
                ->setConfigOptions($this->execBuilderOptions)
                ->build();
        }

        return $executables;
    }
}
