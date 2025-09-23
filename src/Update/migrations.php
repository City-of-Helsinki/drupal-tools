<?php

/**
 * @file
 * Contains update hooks.
 */

declare(strict_types=1);

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

/**
 * Set composer extra.patchLevel.
 */
function drupal_tools_update_5() : UpdateResult {
  $command = ['composer', 'config', 'extra.patchLevel'];

  $process = new Process($command);
  $process->run();

  if ($process->isSuccessful()) {
    return new UpdateResult(['composer extra.patchLevel is already set. Skipping ...']);
  }
  $command[] = '{"drupal/core": "-p2"}';
  $command[] = '--json';

  $process = new Process($command);
  $process->mustRun();

  return new UpdateResult(['Set composer extra.patchLevel configuration.']);
}

/**
 * Remove leftover docker-compose.yml file.
 */
function drupal_tools_update_6(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  $fileManager->removeFiles($options, [
    'docker-compose.yml',
  ]);
  return new UpdateResult(['Attempted to remove docker-compose.yml file.']);
}

/**
 * Update composer/composer.
 */
function drupal_tools_update_7() : UpdateResult {
  $command = ['composer', 'show', 'composer/composer'];

  $process = new Process($command);
  $process->run();

  if (!$process->isSuccessful()) {
    return new UpdateResult(['composer/composer is not installed. Skipping ...']);
  }

  $command = ['composer', 'update', 'composer/composer:2.7.7', '-W'];

  $process = new Process($command);
  $process->mustRun();

  return new UpdateResult(['Updated composer/composer.']);
}

/**
 * Remove dependency to drupal/stage_file_proxy if platform config is installed.
 *
 * Stage file is installed as helfi_platform_config dependency.
 */
function drupal_tools_update_8() : UpdateResult {
  $command = ['composer', 'show', 'drupal/helfi_platform_config'];

  $process = new Process($command);
  $process->run();

  if (!$process->isSuccessful()) {
    return new UpdateResult(['drupal/helfi_platform_config is not installed. Skipping ...']);
  }

  $command = ['composer', 'remove', 'drupal/stage_file_proxy'];

  $process = new Process($command);
  $process->run();

  return new UpdateResult(['Removed drupal/stage_file_proxy.']);
}

/**
 * Ignore https://github.com/advisories/GHSA-mg8j-w93w-xjgc.
 *
 * This does not affect us.
 */
function drupal_tools_update_9() : UpdateResult {
  $command = ['composer', 'config', 'audit.ignore', 'GHSA-mg8j-w93w-xjgc'];
  $process = new Process($command);
  $process->mustRun();

  return new UpdateResult(['Set composer audit.ignore configuration.']);
}

/**
 * Remove direct dependency to drupal/raven module.
 *
 * Raven module is installed as helfi_api_base dependency.
 */
function drupal_tools_update_10() : UpdateResult {
  (new Process(['composer', 'remove', 'drupal/raven']))
    ->run();
  (new Process(['composer', 'update', 'drupal/raven', '-W']))
    ->run();
  return new UpdateResult(['Updated Raven module to latest version.']);
}

/**
 * Update phpstan dependencies.
 *
 * Require dg/bypass-finals library.
 */
function drupal_tools_update_11() : UpdateResult {
  (new Process(['composer', 'update', 'phpstan/phpstan', '-W']))
    ->run();

  return new UpdateResult([
    'Updated phpstan/phpstan and dependencies.',
  ]);
}

/**
 * Re-install dg/bypass-finals as dev-dependency.
 */
function drupal_tools_update_12() : UpdateResult {
  (new Process(['composer', 'remove', 'dg/bypass-finals']))
    ->run();
  (new Process(['composer', 'require', 'dg/bypass-finals:^1.5', '--dev']))
    ->run();

  return new UpdateResult([
    'Re-installed dg/bypass-finals as dev dependency.',
  ]);
}

/**
 * Remove direct dependency to packages provided by drupal/core-dev.
 */
function drupal_tools_update_13() : UpdateResult {
  // Ensure drupal/core-dev is required.
  (new Process(['composer', 'require', 'drupal/core-dev:^10', '--dev', '-W']))
    ->run();

  // For full list of drupal/core-dev dependencies,
  // see: https://packagist.org/packages/drupal/core-dev.
  // Already provided by drupal/core-dev:
  $remove = [
    'phpstan/phpstan',
    'phpstan/extension-installer',
    'mglaman/phpstan-drupal',
    'phpunit/phpunit',
    'drupal/coder',
    'phpspec/prophecy-phpunit',
  ];

  (new Process(array_merge(['composer', 'remove', '--dev'], $remove)))
    ->run();

  return new UpdateResult([
    'Removed direct dependency to ' . implode(', ', $remove),
  ]);
}

/**
 * Allow phpstan/extension-installer. This was accidentally removed update 13.
 */
function drupal_tools_update_14() : UpdateResult {
  // Ensure drupal/core-dev is required.
  (new Process([
    'composer', 'config', 'allow-plugins.phpstan/extension-installer', 'true', '--no-interaction',
  ]))
    ->run();

  return new UpdateResult([
    'Added phpstan/extension-installer to allow-plugins',
  ]);
}

/**
 * Remove druidfi/tools.
 */
function drupal_tools_update_15(UpdateOptions $options, FileManager $fileManager): UpdateResult {
  $fileManager->removeFiles($options, [
    'tools',
  ]);
  return new UpdateResult([
    'Removed tools/ folder',
  ]);
}

/**
 * Remove 'cron-entrypoint.sh' because it's built into Docker image already.
 */
function drupal_tools_update_16(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  $fileManager->removeFiles($options, [
    'docker/openshift/cron-entrypoint.sh',
  ]);

  return new UpdateResult([
    'Removed docker/openshift/cron-entrypoint.sh',
  ]);
}

/**
 * Remove 'custom.locations' because it's built into Docker image.
 */
function drupal_tools_update_17(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  $fileManager->removeFiles($options, [
    'docker/openshift/custom.locations',
  ]);

  return new UpdateResult([
    'Removed docker/openshift/custom.locations',
  ]);
}

/**
 * UHF-11676: Remove 'docker/openshift/crons/linked-events.sh'.
 */
function drupal_tools_update_18(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  $fileManager->removeFiles($options, [
    'docker/openshift/crons/linked-events.sh',
  ]);

  return new UpdateResult([
    'Removed docker/openshift/crons/linked-events.sh',
  ]);
}

/**
 * UHF-11475: Remove direct dependency to drush.
 */
function drupal_tools_update_19(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  (new Process(['composer', 'remove', 'drush/drush']))
    ->run();

  return new UpdateResult([
    'Removed direct dependency to drush',
  ]);
}

/**
 * UHF-11964: Move files to hooks folder.
 */
function drupal_tools_update_20(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  (new Process(['rm', '-r', 'docker/openshift/post-db-replace', 'docker/openshift/deploy']))
    ->run();

  return new UpdateResult([
    'Moved files to hooks folder',
  ]);
}

/**
 * UHF-11150: Allow updating to Drupal 11
 */
function drupal_tools_update_21(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  (new Process(['composer', 'require', 'drupal/core-dev:^10|^11', '--dev']))
    ->run();

  (new Process(['composer', 'require', 'drupal/core-composer-scaffold:^10|^11']))
    ->run();

  // Allow big_pipe_sessionless with mglaman/composer-drupal-lenient.
  // See: https://github.com/mglaman/composer-drupal-lenient.
  (new Process(['composer', 'require', 'mglaman/composer-drupal-lenient']))
    ->run();
  (new Process(['composer', 'config', '--no-plugins', 'allow-plugins.mglaman/composer-drupal-lenient', 'true']))
    ->run();
  (new Process(['composer', 'config', '--merge', '--json', 'extra.drupal-lenient.allowed-list', '["drupal/big_pipe_sessionless"]']))
    ->run();

  // Remove drupal/core from root composer.json. Drupal
  // version is set by drupal/helfi_platform_config.
  // This command should fail with `Removal failed,
  // drupal/core is still present, it may be required
  // by another package.`.
  (new Process(['composer', 'remove', 'drupal/core']))
    ->run();

  return new UpdateResult([
    'Updated Drupal packages.',
  ]);
}
