<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal\CommandProviders;

/**
 * Define the Drupal command provider interface.
 */
interface DrupalCommandProviderInterface
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
     * Execute an arbitrary command.
     *
     * @param null $command
     *   The command to execute.
     */
    public function exec($command = null);

    /**
     * Install a Drupal module.
     *
     * @param mixed $module
     *   A string or array of Drupal modules.
     */
    public function moduleInstall($module);

    /**
     * Remove a Drupal module.
     *
     * @param mixed $module
     *   A string or array of Drupal modules.
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
