<?php

declare(strict_types=1);

namespace DrupalTools\Commands;

use Consolidation\OutputFormatters\FormatterManager;
use DrupalTools\Package\VersionChecker;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\FieldLabels;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'helfi:tools:check-composer-versions')]
#[Bootstrap(level: DrupalBootLevels::NONE)]
final class PackageScannerCommand extends Command {

  use AutowireTrait;
  use FormatterTrait;

  /**
   * Constructs a new instance.
   *
   * @param \DrupalTools\Package\VersionChecker $versionChecker
   *   The version checker service.
   */
  public function __construct(
    private readonly FormatterManager $formatterManager,
    private readonly VersionChecker $versionChecker,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  /*public static function create(DrushContainer $drush): self {
    $formatterManager = $drush->get('formatterManager');

    // @todo Figure out if there's a better way to inject this service.
    if (!$formatterManager->hasFormatter('markdown_table')) {
      $formatterManager->addFormatter('markdown_table', new MarkdownTableFormatter());
    }

    $process = new ComposerOutdatedProcess();
    $versionChecker = new VersionChecker($process);

    return new self($versionChecker);
  }*/

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
  #[Bootstrap(level: DrupalBootLevels::NONE)]
  #[FieldLabels(labels: [
    'name' => 'Name',
    'version' => 'Current version',
    'latest' => 'Latest version',
  ])]
  public function execute(InputInterface $input, OutputInterface $output) : int {
    $rows = [];
    foreach ($this->versionChecker->getOutdated($file) as $version) {
      // Skip dev versions since we can't easily verify the latest version.
      if (str_starts_with($version->version, 'dev-')) {
        continue;
      }

      $rows[] = [
        'name' => $version->name,
        'version' => $version->version,
        'latest' => $version->latestVersion,
      ];
    }

    $exitCode = $rows ? DrushCommands::EXIT_FAILURE_WITH_CLARITY : DrushCommands::EXIT_SUCCESS;

    //return CommandResult::dataWithExitCode(new RowsOfFields($rows), $exitCode);
    return 1;
  }

}
