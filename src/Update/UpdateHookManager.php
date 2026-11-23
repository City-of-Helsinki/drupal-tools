<?php

declare(strict_types=1);

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
   * Makes sure no update hooks are missing after the current schema version.
   *
   * @param int $version
   *   The current schema version.
   *
   * @throws \DrupalTools\Update\MissingUpdateHookException
   */
  private function assertNoMissingHooks(int $version) : void {
    // Function names are returned in lowercase without the leading backslash.
    $prefix = strtolower(ltrim($this->scope, '\\')) . '\drupal_tools_update_';

    $hooks = [];
    foreach (get_defined_functions()['user'] as $function) {
      if (str_starts_with($function, $prefix)) {
        $hooks[] = (int) substr($function, strlen($prefix));
      }
    }
    $pending = array_filter($hooks, fn (int $hook) => $hook > $version);

    if (!$pending || ($oldest = min($pending)) === $version + 1) {
      return;
    }
    throw new MissingUpdateHookException(sprintf(
      'Update hooks %d-%d have been removed.',
      $version + 1,
      $oldest - 1,
    ));
  }

  /**
   * Attempts to run updates.
   *
   * @throws \DrupalTools\Update\MissingUpdateHookException
   */
  public function run(string $schemaFile, UpdateOptions $options) : array {
    if (!$options->runMigrations) {
      return [];
    }
    $version = $this->getSchemaVersion($schemaFile);
    $this->assertNoMissingHooks($version);
    $schema = $version + 1;

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
