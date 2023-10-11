<?php

declare(strict_types = 1);

namespace DrupalTools;

use Symfony\Component\Filesystem\Filesystem;

final class UpdateManager {

  public function __construct(
    private readonly Filesystem $filesystem,
    private readonly FileManager $fileManager,
    private readonly string $schemaFile,
  ) {
  }

  private function getSchemaVersion() : int {
    if (!$this->filesystem->exists($this->schemaFile)) {
      return 0;
    }
    return (int) file_get_contents($this->schemaFile);
  }

  public function run() : void {
    $base = '\DrupalTools\drupal_tools_update';

    for ($i = $this->getSchemaVersion(); $i < 10000; $i++) {
      $function = sprintf('%s_%d', $base, $i);

      if (!function_exists($function)) {
        continue;
      }
      call_user_func($function);
    }
  }

}
