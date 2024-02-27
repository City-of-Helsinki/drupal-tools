<?php

declare(strict_types=1);

namespace DrupalTools\Drush\Commands;

use ComposerLockParser\ComposerInfo;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\helfi_api_base\Package\VersionChecker;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use Psr\Container\ContainerInterface as DrushContainer;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A drush command to check whether given Helfi packages are up-to-date.
 */
final class PackageScannerDrushCommands extends DrushCommands {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\helfi_api_base\Package\VersionChecker $versionChecker
   *   The version checker service.
   * @param \Symfony\Component\Console\Style\OutputStyle $style
   *   The output style.
   */
  public function __construct(
    private readonly VersionChecker $versionChecker,
    private readonly OutputStyle $style,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, DrushContainer $drush): self {
    return new self(
      $container->get('helfi_api_base.package_version_checker'),
      new SymfonyStyle($drush->get('input'), $drush->get('output'))
    );
  }

  /**
   * Checks whether Composer packages are up-to-date.
   *
   * @param string $file
   *   The path to composer.lock file.
   *
   * @return int
   *   The exit code.
   */
  #[Command(name: 'helfi:tools:check-composer-versions')]
  #[Argument(name: 'file', description: 'Path to composer.lock file')]
  public function checkVersions(string $file) : int {
    $info = new ComposerInfo($file);

    $versions = [];
    /** @var \Composer\Package\Package $package */
    foreach (iterator_to_array($info->getPackages()) as $package) {
      $version = $this->versionChecker->get($package->getName(), $package->getVersion());

      // Skip dev versions since we can't easily verify the latest
      // version.
      if (!$version || $version->isLatest || str_starts_with($package->getVersion(), 'dev-')) {
        continue;
      }
      $versions[] = [
        'name' => $package->getName(),
        'currentVersion' => $package->getVersion(),
        'latestVersion' => $version->latestVersion,
      ];
    }
    if (!$versions) {
      return DrushCommands::EXIT_SUCCESS;
    }

    $this->style->table(
      ['Name', 'Version', 'Latest version'],
      $versions,
    );
    return DrushCommands::EXIT_FAILURE_WITH_CLARITY;
  }

}
