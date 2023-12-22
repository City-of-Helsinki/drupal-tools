<?php

/**
 * @file
 * Contains update hooks.
 */

declare(strict_types = 1);

namespace DrupalTools\Update;

use Composer\InstalledVersions;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
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
    if (!InstalledVersions::isInstalled($package)) {
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
    ],
    [
      'composer',
      'require',
      '--dev',
      ...$packages,
      '--no-interaction',
      '--no-audit',
    ],
  ];

  foreach ($commands as $command) {
    $process = new Process($command);

    try {
      $process->run();
    }
    catch (ProcessTimedOutException) {
      // The command will most likely time-out to phpcs composer
      // installer plugin. We don't actually care if it fails or not.
    }
  }
  return new UpdateResult(['Attempted to install: ' . implode(' ', $packages)]);
}

/**
 * Remove leftover files.
 */
function drupal_tools_update_4(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  $fileManager->removeFiles($options, [
    'docker/openshift/crons/migrate-status.php',
  ]);
  return new UpdateResult(['Attempted to remove migrate-status.php file.']);
}

function drupal_tools_update_5() : UpdateResult {
  $command = ['composer', 'config', 'extra.patchLevel'];

  $process = new Process($command);
  $process->run();

  if ($process->isSuccessful()) {
    return new UpdateResult(['Skipped setting composer extra.patchLevel because it is already set.']);
  }
  $command[] = '{"drupal/core": "-p2"}';
  $command[] = '--json';

  $process = new Process($command);
  $process->mustRun();

  return new UpdateResult(['Set composer extra.patchLevel configuration.']);
}
