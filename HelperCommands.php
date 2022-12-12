<?php

declare(strict_types = 1);

namespace Drush\Commands\helfi_drupal_tools;

use Drupal\Component\Serialization\Yaml;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\CommandFailedException;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * A Drush commandfile.
 */
final class HelperCommands extends DrushCommands {

  /**
   * Recursively get all config yml files.
   *
   * @param string $folder
   *   The folder to scan.
   *
   * @return array
   *   The files.
   */
  private function getFilesRecursive(string $folder) {
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($folder)
    );
    $yamlIterator = new \RegexIterator($iterator, '/.+\.yml$/i', \RegexIterator::GET_MATCH);

    $files = [];
    foreach ($yamlIterator as $item) {
      if (!is_array($item)) {
        continue;
      }
      $file = reset($item);

      if (!str_contains($file, '/config/')) {
        continue;
      }

      $files[] = $file;
    }
    return $files;
  }

  /**
   * Clean yml files.
   */
  #[Command(name: 'helfi:tools:clean-yml')]
  public function cleanYmlFiles(string $path) : void {
    if (!$realpath = realpath($path)) {
      throw new CommandFailedException(sprintf('Path %s does not exist.', $path));
    }
    $realpath = rtrim($realpath, '/');

    foreach ($this->getFilesRecursive($realpath) as $file) {
      try {
        $content = Yaml::decode(file_get_contents($file));
      }
      catch (ParseException) {
        continue;
      }

      $hasChanges = FALSE;
      // Remove site specific fields from yaml files.
      foreach (['_core', 'uuid'] as $key) {
        if (!isset($content[$key])) {
          continue;
        }
        unset($content[$key]);
        $hasChanges = TRUE;
      }

      if ($hasChanges) {
        file_put_contents($file, Yaml::encode($content));
      }
    }
  }

}
