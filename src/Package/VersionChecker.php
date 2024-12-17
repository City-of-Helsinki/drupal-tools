<?php

declare(strict_types=1);

namespace DrupalTools\Package;

use DrupalTools\Package\Exception\VersionCheckException;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Provides a package version checker.
 */
class VersionChecker {

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly ComposerOutdatedProcess $process,
  ) {
  }

  /**
   * Gets outdated package versions.
   *
   * @param string $composerLockFile
   *   Path to composer lock file.
   *
   * @return \DrupalTools\Package\Version[]
   *   Outdated packages.
   *
   * @throws \DrupalTools\Package\Exception\VersionCheckException
   */
  public function getOutdated(string $composerLockFile) : array {
    $packages = $this->getPackages($composerLockFile);
    $versions = [];

    foreach ($packages as $packageName => $package) {
      $versions[] = new Version($packageName, $package['latest'], $package['version']);
    }

    return $versions;
  }

  /**
   * Get outdated packages.
   *
   * @throws \DrupalTools\Package\Exception\VersionCheckException
   */
  private function getPackages(string $composerLockFile): array {
    if (!$composerLockFile = realpath($composerLockFile)) {
      throw new VersionCheckException('Composer lock file not found');
    }

    $workingDir = dirname($composerLockFile);
    try {
      $packages = $this->process->run($workingDir);
      $packages = $packages['installed'] ?? [];
    }
    catch (ProcessFailedException) {
      throw new VersionCheckException("Composer process failed");
    }

    $result = [];

    // Key with package name.
    foreach ($packages as $package) {
      $result[$package['name']] = $package;
    }

    return $result;
  }

}
