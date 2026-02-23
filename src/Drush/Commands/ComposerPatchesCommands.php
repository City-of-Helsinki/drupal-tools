<?php

declare(strict_types=1);

namespace DrupalTools\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Drush\Attributes\Argument;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\Command;
use Drush\Attributes\FieldLabels;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

/**
 * A drush command to check composer patches.
 */
final class ComposerPatchesCommands extends DrushCommands {

  /**
   * Checks whether Composer patches are installed correctly.
   *
   * @param string $file
   *   The path to composer.lock file.
   * @param array $options
   *   The options.
   *
   * @return \Consolidation\AnnotatedCommand\CommandResult
   *   The result.
   */
  #[Command(name: 'helfi:tools:check-composer-patches')]
  #[Bootstrap(level: DrupalBootLevels::NONE)]
  #[Argument(name: 'file', description: 'Path to composer.lock file')]
  #[FieldLabels(labels: [
    'name' => 'Name',
    'version' => 'Current version',
    'latest' => 'Latest version',
  ])]
  public function checkVersions(string $file, array $options = ['format' => 'table']) : CommandResult {
  }

}

