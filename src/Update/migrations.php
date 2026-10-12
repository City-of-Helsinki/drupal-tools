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

/**
 * UHF-13425: Allow symfony/runtime to run composer scripts.
 */
function drupal_tools_update_26() : UpdateResult {
  (new Process([
    'composer', 'config', 'allow-plugins.symfony/runtime', 'true', '--no-interaction',
  ]))
    ->run();

  return new UpdateResult([
    'Added symfony/runtime to allow-plugins',
  ]);
}

/**
 * Prevent Composer from running outside of the container.
 */
function drupal_tools_update_27(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  $script = 'tools/composer/require-container.sh';

  // The update hooks run before the default files are updated, so fetch the
  // script first: Composer can't run any commands if the script is missing.
  $fileManager->updateFiles($options, [$script]);

  if (!file_exists($script)) {
    return new UpdateResult([
      sprintf('Skipped the pre-command-run script, since %s is ignored.', $script),
    ]);
  }

  (new Process([
    'composer', 'config', '--no-plugins', 'scripts.pre-command-run', 'bash ' . $script, '--no-interaction',
  ]))
    ->run();

  return new UpdateResult([
    'Added a pre-command-run script that prevents Composer from running outside of the container.',
  ]);
}

/**
 * Ignore enshrined/svg-sanitize 0.22 security advisories.
 *
 * Composer blocks package versions that are affected by security advisories,
 * so drupal/imagecache_external is silently downgraded to 3.0.5, since newer
 * versions require enshrined/svg-sanitize ~0.22. The advisories are fixed in
 * enshrined/svg-sanitize 1.0.
 *
 * We're not affected by the vulnerabilities, since the SVG support is
 * disabled: imagecache_external never passes any SVG files to the sanitizer.
 *
 * @see https://www.drupal.org/project/imagecache_external
 */
function drupal_tools_update_28() : UpdateResult {
  $advisories = [
    // CVE-2026-107380.
    'GHSA-9rjx-3jch-6vjf',
    // CVE-2026-107379.
    'GHSA-v383-3rw5-q8rf',
    // CVE-2026-107381.
    'GHSA-m9xh-6747-9r6f',
    'GHSA-qhmf-972w-m957',
  ];

  $composer = json_decode((string) file_get_contents('composer.json'), TRUE);
  $ignore = $composer['config']['audit']['ignore'] ?? [];

  if (array_is_list($ignore)) {
    $ignore = array_values(array_unique([...$ignore, ...$advisories]));
  }
  else {
    foreach ($advisories as $id) {
      $ignore[$id] ??= 'Not affected: imagecache_external SVG support is disabled.';
    }
  }

  (new Process([
    'composer', 'config', '--json', 'audit.ignore', json_encode($ignore), '--no-interaction',
  ]))
    ->mustRun();

  return new UpdateResult([
    'Added enshrined/svg-sanitize security advisories to composer audit.ignore.',
  ]);
}
