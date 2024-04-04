<?php

declare(strict_types=1);

namespace DrupalTools\Update;

/**
 * A DTO to store update options.
 */
final class UpdateOptions {

  /**
   * Constructs a new instance.
   *
   * @param array $ignoreFiles
   *   Files to ignore. Leave empty to never ignore anything.
   * @param bool $updateExternalPackages
   *   Whether to update external packages.
   * @param bool $selfUpdate
   *   Whether to check if package needs to be updated.
   * @param bool $runMigrations
   *   Whether to run migrations.
   */
  public function __construct(
    public array $ignoreFiles = [],
    public bool $updateExternalPackages = TRUE,
    public bool $selfUpdate = TRUE,
    public bool $runMigrations = TRUE,
  ) {
  }

}
