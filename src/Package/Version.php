<?php

declare(strict_types=1);

namespace DrupalTools\Package;

/**
 * A value object to store package version data.
 */
final class Version {

  /**
   * Constructs a new instance.
   *
   * @param string $name
   *   The package name.
   * @param string $latestVersion
   *   The latest version.
   * @param string $version
   *   The current version.
   */
  public function __construct(
    public string $name,
    public string $latestVersion,
    public string $version,
  ) {
  }

}
