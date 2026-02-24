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
use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Container\ContainerInterface;

/**
 * A drush command to check composer patches.
 */
final class ComposerPatchesCommands extends DrushCommands {

  use FormatterManagerTrait;

  public function __construct(
    private readonly ClientInterface $client,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $drush): self {
    self::populateFormatterManager($drush);

    return new self(
      new Client(),
    );
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
      $this->client->get($url);

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
    $data = json_decode(file_get_contents($file), TRUE);

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
   * @param string $file
   *   The path to 'composer.lock' file.
   * @param array $options
   *   The options.
   *
   * @return \Consolidation\AnnotatedCommand\CommandResult
   *   The result.
   */
  #[Command(name: 'helfi:tools:check-composer-patches')]
  #[Bootstrap(level: DrupalBootLevels::NONE)]
  #[Argument(name: 'file', description: 'Path to composer.lock file')]
  #[FieldLabels(labels: [
    'package' => 'package',
    'description' => 'Patch description',
    'patch' => 'Patch',
  ])]
  public function execute(string $file, array $options = ['format' => 'table']) : CommandResult {
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
      return CommandResult::dataWithExitCode(new RowsOfFields($failed), self::EXIT_FAILURE_WITH_CLARITY);
    }

    return CommandResult::dataWithExitCode(new RowsOfFields($rows), self::EXIT_SUCCESS);
  }

}
