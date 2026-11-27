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

  // Import Node built-in modules with the node prefix.
  $config = (string) file_get_contents($builder);
  $updated = str_replace(
    [
      "import path from 'path';",
      "import { globSync } from 'glob';",
      '// ignore: [],',
    ],
    [
      "import path from 'node:path';",
      "import { globSync } from 'node:fs';",
      '// exclude: [],',
    ],
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

/**
 * Hold back new package releases with the Composer cooldown policy.
 *
 * A compromised release is usually found and removed within a few days, so
 * Composer installs new releases only after the cooldown period. Our own
 * packages are exempt, since they are rolled out by the automatic update
 * workflow. The workflow updates packages with security advisories without
 * the cooldown.
 *
 * Our Satis repository has no publication times, so Composer falls back to
 * the release time of each package.
 *
 * @see https://getcomposer.org/doc/06-config.md#policy
 */
function drupal_tools_update_30() : UpdateResult {
  $messages = [];

  // Composer can't change a repository in the list format, so add the
  // setting after the URL. This keeps the formatting of composer.json.
  $file = (string) file_get_contents('composer.json');
  $updated = preg_replace_callback(
    '#^(\s*)("url":\s*"https://repository\.drupal\.hel\.ninja/?")(,?)$#m',
    fn (array $matches) => sprintf("%s%s,\n%s\"require-published-time\": false%s", $matches[1], $matches[2], $matches[1], $matches[3]),
    $file,
    1,
  );

  if ($updated !== $file && !str_contains($file, '"require-published-time"') && json_decode((string) $updated) !== NULL) {
    file_put_contents('composer.json', $updated);
    $messages[] = 'Composer falls back to the release time of packages from repository.drupal.hel.ninja.';
  }

  $composer = json_decode((string) file_get_contents('composer.json'), TRUE);

  // Keep the cooldown policy of projects that already configure it.
  if (!isset($composer['config']['policy']['cooldown'])) {
    $ignore = [
      'drupal/helfi_*' => 'Our own modules are rolled out by the automatic update workflow.',
      'drupal/hdbt*' => 'Our own themes are rolled out by the automatic update workflow.',
    ];

    // Composer 2.11 and newer support the policy settings.
    (new Process([
      'composer', 'config', '--no-plugins', 'policy.cooldown.period', '3 days', '--no-interaction',
    ]))
      ->mustRun();
    (new Process([
      'composer', 'config', '--no-plugins', '--json', 'policy.cooldown.ignore', json_encode($ignore), '--no-interaction',
    ]))
      ->mustRun();

    $messages[] = 'Added a 3 day Composer cooldown policy for packages other than our own.';
  }

  return new UpdateResult($messages ?: ['The Composer cooldown policy is already configured.']);
}

/**
 * UHF-13657: Remove the svg-sprite override from hdbt_subtheme.
 */
function drupal_tools_update_31() : UpdateResult {
  $file = 'public/themes/custom/hdbt_subtheme/package.json';

  if (!file_exists($file)) {
    return new UpdateResult(['Skipped, hdbt_subtheme has no package.json.']);
  }
  $package = json_decode((string) file_get_contents($file));

  if (!isset($package->overrides->{'svg-sprite'})) {
    return new UpdateResult(['Skipped, hdbt_subtheme has no svg-sprite override.']);
  }
  unset($package->overrides->{'svg-sprite'});

  if (!get_object_vars($package->overrides)) {
    unset($package->overrides);
  }

  $json = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  // Indent with two spaces like npm.
  $json = preg_replace_callback('/^(?: {4})+/m', fn (array $matches) => str_repeat('  ', strlen($matches[0]) / 4), $json);
  file_put_contents($file, $json . "\n");

  return new UpdateResult(['Removed the svg-sprite override from hdbt_subtheme package.json.']);
}

/**
 * Remove ModuleEnabledTest, renamed to DisallowedConfigurationTest.
 */
function drupal_tools_update_32(UpdateOptions $options, FileManager $fileManager) : UpdateResult {
  $fileManager->removeFiles($options, ['tests/dtt/src/ExistingSite/ModuleEnabledTest.php']);

  return new UpdateResult([
    'Removed tests/dtt/src/ExistingSite/ModuleEnabledTest.php. It was replaced by DisallowedConfigurationTest.php.',
  ]);
}

/**
 * Check the hdbt_subtheme lockfile against the theme builder.
 *
 * Old preinstall script caused potential drift between subtheme and
 * theme-builder.
 *
 * For sites using React, moves hdbt install into preinstall (npm resolves
 * file deps before postinstall which might cause issues).
 */
function drupal_tools_update_33() : UpdateResult {
  $file = 'public/themes/custom/hdbt_subtheme/package.json';
  $builder = '../../contrib/hdbt/theme-builder';

  if (!file_exists($file)) {
    return new UpdateResult(['Skipped, hdbt_subtheme has no package.json.']);
  }
  $package = json_decode((string) file_get_contents($file));

  if (!isset($package->dependencies->{'@hdbt/theme-builder'})) {
    return new UpdateResult(['Skipped, hdbt_subtheme does not use the theme builder.']);
  }

  // The scripts call the check from the installed hdbt.
  if (!file_exists('public/themes/contrib/hdbt/theme-builder/tools/check-lock.mjs')) {
    throw new \RuntimeException('The installed drupal/hdbt has no theme-builder/tools/check-lock.mjs. Update drupal/hdbt and run the update again.');
  }

  $scripts = (array) ($package->scripts ?? []);
  $messages = [];

  $check = sprintf('node %s/tools/check-lock.mjs %s', $builder, $builder);
  $preinstall = $check;

  // Move the hdbt install of subthemes with React apps to preinstall.
  $postinstall = $scripts['postinstall'] ?? '';
  if (str_contains($postinstall, 'npm --prefix ../../contrib/hdbt install')) {
    $preinstall .= ' && npm --prefix ../../contrib/hdbt ci --ignore-scripts --no-audit --no-fund';

    if (preg_match('#^npm --prefix \.\./\.\./contrib/hdbt install[^&|;]*$#', $postinstall)) {
      unset($scripts['postinstall']);
    }
    else {
      $messages[] = 'Check the hdbt_subtheme postinstall script, it does more than the hdbt install: ' . $postinstall;
    }
  }

  unset($scripts['preinstall'], $scripts['update-theme-builder']);
  $package->scripts = (object) ([
    'preinstall' => $preinstall,
    'update-theme-builder' => sprintf('rm -rf node_modules/@hdbt/theme-builder && npm install %s --ignore-scripts', $builder),
  ] + $scripts);

  $comments = (array) ($package->{'@comment scripts'} ?? []);
  $comments[] = 'preinstall checks that package-lock.json matches the theme builder from hdbt, since npm ci does not. Run update-theme-builder when it fails.';
  if (!str_contains($preinstall, '--prefix ../../contrib/hdbt ci')) {
    $comments[] = "If the theme bundles hdbt source, for example React components through @/react/common/*, or adds file: dependencies into ../../contrib/hdbt/node_modules, append ' && npm --prefix ../../contrib/hdbt ci --ignore-scripts --no-audit --no-fund' to preinstall.";
  }
  $package->{'@comment scripts'} = array_values(array_unique($comments));

  // Keep the key order of package.json: @comment scripts before scripts.
  $ordered = new \stdClass();
  foreach (get_object_vars($package) as $key => $value) {
    if ($key === 'scripts' && !property_exists($ordered, '@comment scripts')) {
      $ordered->{'@comment scripts'} = $package->{'@comment scripts'};
    }
    if ($key !== '@comment scripts' || !property_exists($ordered, $key)) {
      $ordered->{$key} = $value;
    }
  }

  $json = json_encode($ordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  // Indent with two spaces like npm.
  $json = preg_replace_callback('/^(?: {4})+/m', fn (array $matches) => str_repeat('  ', strlen($matches[0]) / 4), $json);
  file_put_contents($file, $json . "\n");

  $messages[] = 'Updated hdbt_subtheme to check package-lock.json against the theme builder. Run npm run update-theme-builder in hdbt_subtheme if npm ci reports a mismatch.';

  return new UpdateResult($messages);
}
