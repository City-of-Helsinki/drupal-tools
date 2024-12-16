<?php

declare(strict_types=1);

namespace DrupalTools\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\helfi_api_base\Package\VersionChecker;
use DrupalTools\OutputFormatters\MarkdownTableFormatter;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\FieldLabels;
use Drush\Commands\DrushCommands;
use Drush\Drush;
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
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ?DrushContainer $drush = NULL): self {
    if (!$drush && Drush::hasContainer()) {
      $drush = Drush::getContainer();
    }
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
   * Wrapper for `composer outdated` command.
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
  public function checkVersions(?string $file = NULL, array $options = ['format' => 'table']) : CommandResult {
    $rows = [];
    foreach ($this->versionChecker->getOutdated($file) as $version) {
      /** @var \Drupal\helfi_api_base\Package\Version $version */

      // Skip dev versions since we can't easily verify the latest
      // version.
      if ($version->isLatest || str_starts_with($version->version, 'dev-')) {
        continue;
      }

      $rows[] = [
        'name' => $version->name,
        'version' => $version->version,
        'latest' => $version->latestVersion,
      ];
    }

    $exitCode = $rows ? DrushCommands::EXIT_FAILURE_WITH_CLARITY : DrushCommands::EXIT_SUCCESS;

    return CommandResult::dataWithExitCode(new RowsOfFields($rows), $exitCode);
  }

}
