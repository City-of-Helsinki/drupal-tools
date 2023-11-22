<?php

declare(strict_types = 1);

namespace DrupalTools\Update;

use Symfony\Component\Filesystem\Filesystem;

/**
 * A service to manage update hooks.
 */
final class UpdateHookManager {

  /**
   * Constructs a new instance.
   *
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   *   The filesystem.
   * @param \DrupalTools\Update\FileManager $fileManager
   *   The file manager service.
   * @param string $scope
   *   The namespace.
   */
  public function __construct(
    private readonly Filesystem $filesystem,
    private readonly FileManager $fileManager,
    private readonly string $scope = __NAMESPACE__,
  ) {
  }

  /**
   * Gets the current schema version.
   *
   * @return int
   *   The schema version.
   */
  private function getSchemaVersion(string $schemaFile) : int {
    if (!$this->filesystem->exists($schemaFile)) {
      return 0;
    }
    return (int) file_get_contents($schemaFile);
  }

  /**
   * Updates the schema version.
   *
   * @param int $version
   *   The current schema version.
   * @param string $schemaFile
   *   The schema file.
   */
  private function updateSchemaVersion(int $version, string $schemaFile) : void {
    $this->filesystem->dumpFile($schemaFile, (string) $version);
  }

  /**
   * Attempts to run updates.
   */
  public function run(string $schemaFile, UpdateOptions $options) : array {
    if (!$options->runMigrations) {
      return [];
    }
    $schema = ($this->getSchemaVersion($schemaFile) + 1);

    $results = [];
    for ($i = $schema; $i < 1000; $i++) {
      $function = sprintf('%s\drupal_tools_update_%d', $this->scope, $i);

      if (!function_exists($function)) {
        break;
      }
      $results[] = call_user_func($function,
        $options,
        $this->fileManager,
        $this->filesystem
      );
      $this->updateSchemaVersion($i, $schemaFile);
    }
    return $results;
  }

}
