<?php

declare(strict_types=1);

namespace DrupalTools\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use DrupalTools\Package\Exception\VersionCheckException;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\FieldLabels;
use Drush\Attributes\Formatter;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A Drush command to check composer patches.
 */
#[AsCommand(
  name: 'helfi:tools:check-composer-patches',
  description: 'Checks whether Composer patches are installed correctly.',
)]
#[Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
#[FieldLabels(labels: [
  'package' => 'package',
  'description' => 'Patch description',
  'patch' => 'Patch',
])]
#[Bootstrap(level: DrupalBootLevels::ROOT)]
final class ComposerPatchesCommand extends Command {

  use AutowireTrait;
  use FormatterTrait;

  public function __construct(
    private readonly ClientInterface $client,
    protected readonly FormatterManager $formatterManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addArgument('file', InputArgument::REQUIRED, 'Path to composer.lock file');
  }

  /**
   * Checks if the patch is valid.
   *
   * @param string $patch
   *   The patch to validate.
   *
   * @return bool
   *   TRUE if patch is valid.
   */
  private function isValidPatch(string $patch): bool {
    $validDirectories = [
      './public/',
      './patches/',
    ];

    foreach ($validDirectories as $directory) {
      if (str_starts_with($patch, $directory)) {
        return TRUE;
      }
    }

    // Support old Drupal.org patch workflow.
    if (str_starts_with($patch, 'https://www.drupal.org/files/issues/')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Validates if the package is allowed to add patches.
   *
   * @param string $package
   *   The package to validate.
   *
   * @return bool
   *   TRUE if the package is valid.
   */
  private function isValidPackage(string $package): bool {
    $url = sprintf('https://repository.drupal.hel.ninja/p2/%s~dev.json', $package);

    try {
      // Make sure the package exists in our Composer repository.
      $this->client->request('GET', $url);

      return TRUE;
    }
    catch (GuzzleException) {
    }
    return FALSE;
  }

  /**
   * Gets the patched packages from 'composer.lock' file.
   *
   * @param string $file
   *   The 'composer.lock' file.
   *
   * @return array
   *   An array of patched packages.
   */
  private function getPatchedPackages(string $file): array {
    $data = json_decode(file_get_contents($file), TRUE, flags: JSON_THROW_ON_ERROR);
    $packages = [];

    foreach ($data['packages'] as $package) {
      if (!isset($package['extra']['patches'])) {
        continue;
      }
      ['name' => $name] = $package;

      foreach ($package['extra']['patches'] as $library => $patches) {
        foreach ($patches as $description => $patch) {
          $packages[$name][] = [
            'package' => $library,
            'description' => $description,
            'patch' => $patch,
          ];
        }
      }
    }
    return $packages;
  }

  /**
   * Checks whether Composer patches are installed correctly.
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
   * The actual execute command.
   *
   * @param string $file
   *   The composer.lock file.
   *
   * @return \Consolidation\AnnotatedCommand\CommandResult
   *   The command result.
   *
   * @throws \JsonException
   */
  public function doExecute(string $file): CommandResult {
    if (!realpath($file) || !file_exists($file)) {
      throw new VersionCheckException('Composer lock file not found');
    }
    $rows = $failed = [];

    $packages = $this->getPatchedPackages($file);

    foreach ($packages as $name => $patches) {
      $rows[] = [
        'package' => '<info>' . $name . '</info>',
        'description' => '',
        'patch' => '',
      ];

      if (!$this->isValidPackage($name)) {
        $failed[] = [
          'package' => $name,
          'description' => 'This package is not allowed to add patches.',
          'patch' => '',
        ];
      }

      foreach ($patches as $patch) {
        $row = [
          'package' => $patch['package'],
          'description' => $patch['description'],
          'patch' => $patch['patch'],
        ];

        if (!$this->isValidPatch($patch['patch'])) {
          $failed[] = $row;
        }
        $rows[] = $row;
      }
    }

    if ($failed) {
      return CommandResult::dataWithExitCode(new RowsOfFields($failed), self::FAILURE);
    }
    return CommandResult::dataWithExitCode(new RowsOfFields($rows), self::SUCCESS);
  }

}
