<?php

declare(strict_types=1);

namespace DrupalTools\Drush\Commands;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\FieldLabels;
use Drush\Attributes\Formatter;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: self::NAME,
  description: 'Checks whether Composer packages are up-to-date and formats the results',
)]
#[Bootstrap(level: DrupalBootLevels::NONE)]
#[FieldLabels(labels: [
  'name' => 'Name',
  'version' => 'Current version',
  'latest' => 'Latest version',
])]
#[Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class PackageScannerCommands extends Command {

  use AutowireTrait;
  use FormatterTrait;

  public const string NAME = 'helfi:tools:check-composer-versions';

  public function __construct(
    private readonly FormatterManager $formatterManager,
  ) {
    parent::__construct();
  }

  public function configure(): void {
    $this->addArgument('file', InputArgument::REQUIRED);
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

  private function doExecute(string $file): RowsOfFields {
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
  public function execute(InputInterface $input, OutputInterface $output) : int {
    $result = $this->doExecute($input->getArgument('file'));
    $this->writeFormattedOutput($input, $output, $this->doExecute($input->getArgument('file')));
  }

}
