<?php

declare(strict_types = 1);

namespace Drush\Commands\helfi_drupal_tools;

use Composer\InstalledVersions;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
final class ComposerCommands extends DrushCommands {

  /**
   * Gets the list of installed packages.
   *
   * @command helfi:composer:list-packages
   *
   * @return int
   *   The exit code.
   */
  private function getInstalledPackages() : array {
    return array_filter(InstalledVersions::getInstalledPackages(), function (string $package) {
      return str_starts_with($package, 'drupal/hdbt') || str_starts_with($package, 'drupal/helfi_');
    });
  }

  /**
   * Gets the update string for composer to all update packages to 'dev-main'.
   *
   * @command helfi:composer:get-update-string
   *
   * @return int
   *   The exit code.
   */
  public function getUpdateString() : int {
    $packages = $this->getInstalledPackages();
    $this->io()->write(implode(':dev-main ', $packages));

    return self::EXIT_SUCCESS;
  }

}
