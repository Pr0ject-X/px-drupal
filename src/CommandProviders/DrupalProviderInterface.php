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
    public function moduleInstall($module);

    /**
     * Remove a Drupal module.
     *
     * @param string $module
     *   The Drupal module name.
     */
    public function moduleRemove($module);

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

    /**
     * Install the Drupal core application.
     *
     * @param string $dbUrl
     *   The Drupal database URL.
     * @param string $profile
     *   The Drupal install profile.
     * @param array $options
     *   An array of Drupal database options.
     */
    public function install(string $dbUrl, string $profile = 'standard', array $options = []);
}
