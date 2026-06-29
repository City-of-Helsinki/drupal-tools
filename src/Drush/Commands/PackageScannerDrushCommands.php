<?php

declare(strict_types=1);

namespace DrupalTools\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use DrupalTools\OutputFormatters\FormatterManagerTrait;
use DrupalTools\Package\ComposerOutdatedProcess;
use DrupalTools\Package\VersionChecker;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\FieldLabels;
use Drush\Attributes\Formatter;
use Drush\Boot\DrupalBootLevels;
use Drush\Formatters\FormatterTrait;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A drush command to check whether given Helfi packages are up-to-date.
 */
#[AsCommand(
  name: 'helfi:tools:check-composer-versions',
)]
#[Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
#[FieldLabels(labels: [
  'name' => 'Name',
  'version' => 'Current version',
  'latest' => 'Latest version',
])]
#[Bootstrap(level: DrupalBootLevels::NONE)]
final class PackageScannerDrushCommands extends Command {

  use FormatterManagerTrait;
  use FormatterTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Consolidation\OutputFormatters\FormatterManager $formatterManager
   *   The formatter manager.
   * @param \DrupalTools\Package\VersionChecker $versionChecker
   *   The version checker service.
   */
  public function __construct(
    protected readonly FormatterManager $formatterManager,
    private readonly VersionChecker $versionChecker,
  ) {
    $this->populateFormatterManager();

    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $drush): self {
    $process = new ComposerOutdatedProcess();
    $versionChecker = new VersionChecker($process);

    return new self($drush->get(FormatterManager::class), $versionChecker);
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addArgument('file', InputArgument::REQUIRED, 'Path to composer.lock file');
  }

  /**
   * Checks whether Composer packages are up-to-date.
   *
   * Wrapper for `composer outdated` command.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The input.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The output.
   *
   * @return int
   *   The exit code.
   */
  public function execute(InputInterface $input, OutputInterface $output) : int {
    $file = (string) $input->getArgument('file');

    $result = $this->doExecute($file);
    $this->writeFormattedOutput($input, $output, $result->getOutputData());

    return $result->getExitCode();
  }

  /**
   * Checks whether Composer packages are up-to-date.
   *
   * Wrapper for `composer outdated` command.
   *
   * @param string $file
   *   The path to composer.lock file.
   *
   * @return \Consolidation\AnnotatedCommand\CommandResult
   *   The result.
   */
  public function doExecute(string $file) : CommandResult {
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

    $exitCode = $rows ? self::FAILURE : self::SUCCESS;

    return CommandResult::dataWithExitCode(new RowsOfFields($rows), $exitCode);
  }

}
