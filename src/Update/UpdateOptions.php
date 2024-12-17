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
   * @param string $branch
   *   Helfi platform branch for file updates.
   * @param bool $updateExternalPackages
   *   Whether to update external packages.
   * @param bool $selfUpdate
   *   Whether to check if package needs to be updated.
   * @param bool $runMigrations
   *   Whether to run migrations.
   * @param bool $isCI
   *   Whether we're operating in a CI environment.
   */
  public function __construct(
    public array $ignoreFiles = [],
    public string $branch = 'main',
    public bool $updateExternalPackages = TRUE,
    public bool $selfUpdate = TRUE,
    public bool $runMigrations = TRUE,
    public bool $isCI = FALSE,
  ) {
  }

}
