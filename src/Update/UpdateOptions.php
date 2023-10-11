<?php

declare(strict_types = 1);

namespace DrupalTools\Update;

/**
 * A DTO to store update options.
 */
final class UpdateOptions {

  /**
   * Constructs a new instance.
   *
   * @param bool $ignoreFiles
   *   Whether to allow files to be ignored.
   * @param bool $updateDist
   *   Whether to update '.dist' files.
   * @param bool $updateExternalPackages
   *   Whether to update external packages.
   * @param bool $selfUpdate
   *   Whether to check if package needs to be updated.
   * @param bool $runMigrations
   *   Whether to run migrations.
   */
  public function __construct(
    public readonly bool $ignoreFiles,
    public readonly bool $updateDist,
    public readonly bool $updateExternalPackages,
    public readonly bool $selfUpdate,
    public readonly bool $runMigrations,
  ) {
  }

}
