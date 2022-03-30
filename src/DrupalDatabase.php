<?php

declare(strict_types=1);

namespace Pr0jectX\PxDrupal;

use Pr0jectX\Px\ProjectX\Plugin\EnvironmentType\EnvironmentDatabase;

/**
 * Define the Drupal database object.
 */
class DrupalDatabase
{
    /**
     * @var \Pr0jectX\Px\ProjectX\Plugin\EnvironmentType\EnvironmentDatabase
     */
    protected $database;

    /**
     * The Drupal database constructor.
     *
     * @param \Pr0jectX\Px\ProjectX\Plugin\EnvironmentType\EnvironmentDatabase $database
     */
    public function __construct(EnvironmentDatabase $database)
    {
        if (!$database->isValid()) {
            throw new \RuntimeException(
                'The provide database object is invalid'
            );
        }
        $this->database = $database;
    }

    /**
     * Retrieve the Drupal database information.
     *
     * @return array
     *   An array of the Drupal database properties.
     */
    public function databaseInfo(): array
    {
        return $this->database->getProperties();
    }

    /**
     * Retrieve the Drupal database URL.
     *
     * @return string
     *   The Drupal database URL.
     */
    public function databaseUrl(): string
    {
        $dbSchema = "{$this->database->getType()}://";
        $dbLocation = "{$this->database->getHost()}:{$this->database->getPort()}";
        $dbAccessAuth = "{$this->database->getUsername()}:{$this->database->getPassword()}";

        return "{$dbSchema}{$dbAccessAuth}@{$dbLocation}/{$this->database->getDatabase()}";
    }
}
