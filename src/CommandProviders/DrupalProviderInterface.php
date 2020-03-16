<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\CommandProviders;

/**
 * Define the Drupal provider interface.
 */
interface DrupalProviderInterface
{
    /**
     * Login to the Drupal application.
     */
    public function login();

    /**
     * Clear the Drupal cache.
     */
    public function cacheRebuild();

    /**
     * Install a Drupal module.
     *
     * @param string $module
     *   The Drupal module name.
     */
    public function moduleInstall(string $module);

    /**
     * Remove a Drupal module.
     *
     * @param string $module
     *   The Drupal module name.
     */
    public function moduleRemove(string $module);

    /**
     * Create the Drupal account.
     *
     * @param string $username
     *   The Drupal account username.
     * @param array $options
     *   The Drupal account options.
     */
    public function createAccount(string $username, array $options = []);

    /**
     * Execute an arbitrary Drupal command.
     *
     * @param null $command
     *   The command to execute.
     */
    public function exec($command = null);
}
