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
   * Clean yml files.
   */
  #[Command(name: 'helfi:tools:clean-yml')]
  public function cleanYmlFiles(string $path) : void {
    if (!$realpath = realpath($path)) {
      throw new CommandFailedException(sprintf('Path %s does not exist.', $path));
    }
    $realpath = rtrim($realpath, '/');

    foreach (new \DirectoryIterator($realpath) as $file) {
      if (!$file->isFile()) {
        continue;
      }
      $filePath = sprintf('%s/%s', $realpath, $file->getFilename());

      try {
        $content = Yaml::decode(file_get_contents($filePath));
      }
      catch (ParseException) {
        continue;
      }

      // Remove site specific fields from yaml files.
      foreach (['_core', 'uuid'] as $key) {
        if (!isset($content[$key])) {
          continue;
        }
        unset($content[$key]);
      }
      file_put_contents($filePath, Yaml::encode($content));
    }

  }

}
