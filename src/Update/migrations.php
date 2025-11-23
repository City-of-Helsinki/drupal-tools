<?php

/**
 * @file
 * Contains update hooks.
 */

declare(strict_types=1);

namespace DrupalTools\Update;

use Symfony\Component\Process\Process;

/**
 * UHF-11501: Allow updating to Drupal 11.
 */
function drupal_tools_update_21(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  (new Process(['composer', 'require', 'drupal/core-dev:^10|^11', '--dev']))
    ->run();

  (new Process(['composer', 'require', 'drupal/core-composer-scaffold:^10|^11']))
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

/**
 * UHF-11501: Install mglaman/composer-drupal-lenient.
 */
function drupal_tools_update_22(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  // Allow big_pipe_sessionless with mglaman/composer-drupal-lenient.
  // See: https://github.com/mglaman/composer-drupal-lenient.
  (new Process(['composer', 'require', 'mglaman/composer-drupal-lenient']))
    ->run();
  (new Process(['composer', 'config', '--no-plugins', 'allow-plugins.mglaman/composer-drupal-lenient', 'true']))
    ->run();

  // Override modules that are currently blocking D11 update.
  (new Process([
    'composer',
    'config',
    '--merge',
    '--json',
    'extra.drupal-lenient.allowed-list',
    '["drupal/big_pipe_sessionless"]',
  ]))
    ->run();

  return new UpdateResult([
    'Installed mglaman/composer-drupal-lenient.',
  ]);
}

/**
 * UHF-11150: Update hdbt subtheme core_version_requirement.
 */
function drupal_tools_update_23(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  // Update core_version_requirement in hdbt_subtheme.
  (new Process([
    'sed',
    '-i',
    's/^core_version_requirement: .*/core_version_requirement: ^10 || ^11/',
    'public/themes/custom/hdbt_subtheme/hdbt_subtheme.info.yml',
  ]))
    ->mustRun();

  return new UpdateResult([
    'Updated hdbt_subtheme.info.yml.',
  ]);
}

/**
 * Update composer installers to new major version.
 */
function drupal_tools_update_24(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  (new Process([
    'composer',
    'require',
    'composer/installers',
    '^2.0',
    '-W',
  ]))
    ->run();

  return new UpdateResult([
    'Updated composer/installers to ^2.0',
  ]);
}

/**
 * Remove phpunit.platform.xml.
 */
function drupal_tools_update_25(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  $fileManager->removeFiles($options, ['phpunit.platform.xml']);

  return new UpdateResult([
    'Removed unused phpunit.platform.xml file.',
  ]);
}
