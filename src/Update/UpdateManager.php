<?php

declare(strict_types = 1);

namespace DrupalTools\Update;

use Symfony\Component\Filesystem\Filesystem;

/**
 * A service to manage update hooks.
 */
final class UpdateManager {

  /**
   * Constructs a new instance.
   *
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   *   The filesystem.
   * @param \DrupalTools\Update\FileManager $fileManager
   *   The file manager service.
   * @param string $schemaFile
   *   The file to store the current schema version.
   */
  public function __construct(
    private readonly Filesystem $filesystem,
    private readonly FileManager $fileManager,
    private readonly string $schemaFile,
  ) {
  }

  /**
   * Gets the current schema version.
   *
   * @return int
   *   The schema version.
   */
  private function getSchemaVersion() : int {
    if (!$this->filesystem->exists($this->schemaFile)) {
      return 0;
    }
    return (int) file_get_contents($this->schemaFile);
  }

  /**
   * Updates the schema version.
   *
   * @param int $version
   *   The current schema version.
   */
  private function updateSchemaVersion(int $version) : void {
    $this->filesystem->dumpFile($this->schemaFile, $version);
  }

  /**
   * Attempts to run updates.
   */
  public function run(UpdateOptions $options) : array {
    if (!$options->runMigrations) {
      return [];
    }
    $schema = ($this->getSchemaVersion() + 1);

    $results = [];
    for ($i = $schema; $i < 1000; $i++) {
      $function = sprintf('%s\drupal_tools_update_%d', __NAMESPACE__, $i);

      if (!function_exists($function)) {
        break;
      }
      $results[] = call_user_func($function,
        $options,
        $this->fileManager,
        $this->filesystem
      );
      $this->updateSchemaVersion($i);
    }
    return $results;
  }

}
