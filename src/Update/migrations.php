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
 * @see https://git.drupalcode.org/project/imagecache_external/-/work_items/3599127
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

/**
 * UHF-13657: Use the hdbt theme builder in hdbt_subtheme.
 */
function drupal_tools_update_29() : UpdateResult {
  $theme = 'public/themes/custom/hdbt_subtheme';
  $file = $theme . '/package.json';
  $builder = $theme . '/theme-builder.mjs';

  if (!file_exists($file) || !file_exists($builder)) {
    return new UpdateResult(['Skipped, hdbt_subtheme does not use the theme builder.']);
  }
  $package = json_decode((string) file_get_contents($file));

  // Use the Node glob instead of the glob package.
  $config = (string) file_get_contents($builder);
  $updated = str_replace(
    ["import { globSync } from 'glob';", '// ignore: [],'],
    ["import { globSync } from 'node:fs';", '// exclude: [],'],
    $config,
  );
  if ($updated !== $config) {
    file_put_contents($builder, $updated);
  }

  // Refresh the theme builder from hdbt on every install.
  $scripts = ['preinstall' => 'rm -rf node_modules/@hdbt/theme-builder && npm install ../../contrib/hdbt/theme-builder --loglevel=silent'];
  foreach ($package->scripts as $name => $script) {
    $scripts[$name] ??= str_replace('../../contrib/hdbt/tools/sandbox.sh', 'sandbox', $script);
  }

  // Allow subthemes without JavaScript files.
  $scripts = str_replace(
    ['sandbox biome lint src/js/', 'sandbox biome format --write src/js/'],
    [
      'sandbox biome lint --no-errors-on-unmatched src/js/',
      'sandbox biome format --write --no-errors-on-unmatched src/js/',
    ],
    $scripts,
  );
  $package->scripts = (object) $scripts;

  if (isset($package->{'@comment scripts'})) {
    $package->{'@comment scripts'} = str_replace('the hdbt sandbox script', 'the sandbox command', $package->{'@comment scripts'});
  }
  $package->scripts->{'lint:scss'} = 'sandbox stylelint "src/scss/**/*.scss" --config node_modules/@hdbt/theme-builder/.stylelintrc.yaml --formatter string --fix';
  unset($package->scripts->{'lint:stylelint'});

  // Keep the hdbt install for React apps.
  if (!file_exists($theme . '/tsconfig.json')) {
    unset($package->scripts->postinstall);
  }

  $package->dependencies ??= new \stdClass();

  // Remove the packages the theme builder provides.
  foreach (['@biomejs/biome', 'breakpoint-sass', 'hds-design-tokens'] as $name) {
    unset($package->dependencies->{$name});
  }
  if (!str_contains($updated, "from 'glob'")) {
    unset($package->dependencies->glob);
  }
  $package->dependencies->{'@hdbt/theme-builder'} = 'file:../../contrib/hdbt/theme-builder';

  $package->overrides ??= new \stdClass();
  $package->overrides->{'svg-sprite'} = (object) ['glob' => '^13.0.0'];

  $package->allowScripts ??= new \stdClass();
  $package->allowScripts->esbuild ??= FALSE;
  $package->allowScripts->{'@parcel/watcher'} ??= FALSE;

  $json = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  // Indent with two spaces like npm.
  $json = preg_replace_callback('/^(?: {4})+/m', fn (array $matches) => str_repeat('  ', strlen($matches[0]) / 4), $json);
  file_put_contents($file, $json . "\n");

  return new UpdateResult([
    'Updated hdbt_subtheme package.json and theme-builder.mjs to use the hdbt theme builder.',
  ]);
}
