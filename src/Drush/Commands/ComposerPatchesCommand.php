<?php

declare(strict_types=1);

namespace DrupalTools\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use DrupalTools\OutputFormatters\FormatterManagerTrait;
use DrupalTools\Package\Exception\VersionCheckException;
use Drush\Attributes\Argument;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\Command;
use Drush\Attributes\FieldLabels;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A drush command to check composer patches.
 */
#[Bootstrap(level: DrupalBootLevels::NONE)]
#[AsCommand(
  name: 'helfi:tools:check-composer-patches',
  description: 'Checks whether Composer patches are installed correctly.',
)]
final class ComposerPatchesCommand extends \Symfony\Component\Console\Command\Command {

  use AutowireTrait;
  use FormatterTrait;

  public function __construct(
    private readonly ClientInterface $client,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('file', InputArgument::REQUIRED, 'Path to composer.lock file');
  }

  /**
   * {@inheritdoc}
   */
  /*public static function create(ContainerInterface $drush): self {
    self::populateFormatterManager($drush);

    return new self(
      new Client(),
    );
  }*/

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
  #[Argument(name: 'file', description: 'Path to composer.lock file')]
  #[FieldLabels(labels: [
    'package' => 'package',
    'description' => 'Patch description',
    'patch' => 'Patch',
  ])]
  public function execute(InputInterface $input, OutputInterface $output) : int {
    $file = $input->getArgument('file');

    if (!realpath($file)) {
      throw new VersionCheckException('Composer lock file not found');
    }
    $rows = $failed = [];

    foreach ($this->getPatchedPackages($file) as $name => $patches) {
      $rows[] = [
        'package' => $name,
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
      $this->writeFormattedOutput($input, $output, new RowsOfFields($failed));
      return self::FAILURE;
    }

    $this->writeFormattedOutput($input, $output, new RowsOfFields($rows));
    return self::SUCCESS;
  }

}
