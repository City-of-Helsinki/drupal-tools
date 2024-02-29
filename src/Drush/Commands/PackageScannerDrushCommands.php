<?php

declare(strict_types=1);

namespace DrupalTools\Drush\Commands;

use ComposerLockParser\ComposerInfo;
use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\helfi_api_base\Package\VersionChecker;
use DrupalTools\OutputFormatters\MarkdownTableFormatter;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\FieldLabels;
use Drush\Commands\DrushCommands;
use Psr\Container\ContainerInterface as DrushContainer;

/**
 * A drush command to check whether given Helfi packages are up-to-date.
 */
final class PackageScannerDrushCommands extends DrushCommands {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\helfi_api_base\Package\VersionChecker $versionChecker
   *   The version checker service.
   */
  public function __construct(
    private readonly VersionChecker $versionChecker,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, DrushContainer $drush): self {
    /** @var \Drush\Formatters\DrushFormatterManager $formatterManager */
    $formatterManager = $drush->get('formatterManager');

    // @todo Figure out if there's a better way to inject this service.
    if (!$formatterManager->hasFormatter('markdown_table')) {
      $formatterManager->addFormatter('markdown_table', new MarkdownTableFormatter());
    }
    return new self(
      $container->get('helfi_api_base.package_version_checker'),
    );
  }

  /**
   * Checks whether Composer packages are up-to-date.
   *
   * @param string $file
   *   The path to composer.lock file.
   * @param array $options
   *   The options.
   *
   * @return \Consolidation\AnnotatedCommand\CommandResult
   *   The result.
   */
  #[Command(name: 'helfi:tools:check-composer-versions')]
  #[Argument(name: 'file', description: 'Path to composer.lock file')]
  #[FieldLabels(labels: [
    'name' => 'Name',
    'version' => 'Current version',
    'latest' => 'Latest version',
  ])]
  public function checkVersions(string $file, array $options = ['format' => 'table']) : CommandResult {
    $info = new ComposerInfo($file);

    $rows = [];
    /** @var \Composer\Package\Package $package */
    foreach (iterator_to_array($info->getPackages()) as $package) {
      $version = $this->versionChecker->get($package->getName(), $package->getVersion());

      // Skip dev versions since we can't easily verify the latest
      // version.
      if (!$version || $version->isLatest || str_starts_with($package->getVersion(), 'dev-')) {
        continue;
      }
      $rows[] = [
        'name' => $package->getName(),
        'version' => $package->getVersion(),
        'latest' => $version->latestVersion,
      ];
    }

    $exitCode = $rows ? DrushCommands::EXIT_FAILURE_WITH_CLARITY : DrushCommands::EXIT_SUCCESS;

    return CommandResult::dataWithExitCode(new RowsOfFields($rows), $exitCode);
  }

}
