<?php

/**
 * @file
 * Contains update hooks.
 */

declare(strict_types = 1);

namespace DrupalTools\Update;

use Symfony\Component\Process\Process;

/**
 * Remove leftover files.
 */
function drupal_tools_update_1(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  $remove = [
    'docker/openshift/crons/drupal.sh',
    'docker/local/',
    '.github/workflows/robo.yml.dist',
    '.github/workflows/robo.yml',
    'tools/make/project/robo.mk',
  ];

  $fileManager->removeFiles($options, $remove);
  $messages = array_map(fn (string $file) => sprintf('Attempted to remove %s file', $file), $remove);
  return new UpdateResult($messages);
}

/**
 * Set composer 'audit.abandoned' config to 'report'.
 */
function drupal_tools_update_2() : UpdateResult {
  $process = new Process(['composer', 'config', 'audit.abandoned', 'report']);
  $process->run();

  if (!$process->isSuccessful()) {
    throw new \InvalidArgumentException(
      sprintf('Failed to set composer config audit.abandoned setting. This requires composer version 2.6.5 or higher: %s', $process->getErrorOutput())
    );
  }
  $messages = [
    'Ran composer config audit.abandoned report',
    'This setting will default to "fail" in composer 2.7 version and would cause our GitHub actions to fail because couple of our dependencies ship with abandoned dependencies.',
  ];

  return new UpdateResult($messages);

}

/**
 * Install phpstan extensions.
 */
function drupal_tools_update_3() : UpdateResult {
  $packages = [
    'jangregor/phpstan-prophecy',
    'mglaman/phpstan-drupal',
    'phpstan/extension-installer',
    'phpstan/phpstan',
    'phpstan/phpstan-deprecation-rules',
  ];

  $missing = FALSE;
  foreach ($packages as $package) {
    $process = new Process(['composer', 'show', $package]);
    $process->run();

    if (!$process->isSuccessful() || $process->getExitCode() > 0) {
      $missing = TRUE;
      break;
    }
  }

  if (!$missing) {
    return new UpdateResult(['Skipped update because all dependencies are already installed.']);
  }

  $commands = [
    [
      'composer',
      'config',
      '--no-plugins',
      'allow-plugins.phpstan/extension-installer',
      'true',
      '--no-interaction',
      '--no-scripts',
      '-vvvv',
    ],
    [
      'composer',
      'require',
      '--dev',
      ...$packages,
      '--no-interaction',
      '--no-scripts',
      '--no-audit',
      '-vvvv',
    ],
  ];

  foreach ($commands as $command) {
    $process = new Process($command);
    $process->setTty(TRUE);
    $process->run();

    if (!$process->isSuccessful()) {
      throw new \InvalidArgumentException($process->getErrorOutput());
    }
  }

  return new UpdateResult();
}
