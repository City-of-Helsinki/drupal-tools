<?php

declare(strict_types = 1);

namespace DrupalTools;

final class UpdateOptions {

  public function __construct(
    public readonly bool $ignoreFiles,
    public readonly bool $updateDist,
    public readonly bool $updateExternalPackages,
    public readonly bool $selfUpdate,
    public readonly bool $runMigrations,
  ) {
  }
}
