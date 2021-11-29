<?php

declare(strict_types = 1);

namespace Drush\Commands\helfi_drupal_tools;

use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use Symfony\Component\Process\Process;

/**
 * A Drush commandfile.
 */
final class SelfUpdateCommands extends DrushCommands {

  private const BASE_URL = 'https://raw.githubusercontent.com/City-of-Helsinki/drupal-helfi-platform/main/';

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private $httpClient;

  /**
   * Gets the http client.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The http client.
   */
  private function httpClient() : ClientInterface {
    if (!$this->httpClient) {
      $this->httpClient = new Client(['base_uri' => self::BASE_URL]);
    }
    return $this->httpClient;
  }

  /**
   * Copies source file to destination.
   *
   * @param string $source
   *   The source.
   * @param string $destination
   *   The destination.
   *
   * @return bool
   *   TRUE if succeeded, FALSE if not.
   */
  private function copyFile(string $source, string $destination) : bool {
    try {
      $resource = Utils::tryFopen($destination, 'w');
      $this->httpClient()->request('GET', $source, ['sink' => $resource]);
    }
    catch (GuzzleException $e) {
      $this->io()->error($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Parses given options.
   *
   * @param array $options
   *   The options to parse.
   *
   * @return array
   *   The options.
   */
  private function parseOptions(array $options) : array {
    foreach ($options as $key => $value) {
      // Convert (string) true and false to proper booleans.
      if (is_string($value) && (strtolower($value) === 'true' || strtolower($value) === 'false')) {
        $options[$key] = strtolower($value) === 'true';
      }
    }
    return $options;
  }

  /**
   * Updates files from platform.
   *
   * @param bool $updateDist
   *   Whether to update dist files or not.
   *
   * @return $this
   *   The self.
   */
  private function updateFiles(bool $updateDist) : self {
    $map = [
      '.github/workflows/test.yml.dist' => '.github/workflows/test.yml',
      '.github/workflows/artifact.yml.dist' => '.github/workflows/artifact.yml',
      '.github/workflows/update-config.yml.dist' => '.github/workflows/update-config.yml',
      '.gitignore.dist' => '.gitignore',
    ];
    $map += [
      'public/sites/default/settings.php',
      'docker/openshift/custom.locations',
      'docker/openshift/Dockerfile',
      'docker/openshift/entrypoints/20-deploy.sh',
      'docker/openshift/crons/drupal.sh',
      'docker/openshift/crons/migrate-status.php',
      'docker/openshift/crons/migrate-tpr.sh',
      'docker/openshift/crons/prestop-hook.sh',
      'docker/openshift/crons/purge-queue.sh',
      'docker/openshift/crons/update-translations.sh',
      'docker-compose.yml',
      'phpunit.xml.dist',
      'phpunit.platform.xml',
    ];

    foreach ($map as $source => $destination) {
      // Fallback source to destination if source is not defined.
      if (is_numeric($source)) {
        $source = $destination;
      }
      $isDist = $this->fileIsDist($source);

      // Update the dist file if the original .dist file exists and the
      // non-dist one does not.
      if ($isDist && (file_exists($source) && !file_exists($destination))) {
        $this->copyFile($source, $source);
        continue;
      }

      // Skip updating .dist files if configured so.
      if (!$updateDist && $isDist) {
        continue;
      }
      if (!$this->copyFile($source, $destination)) {
        throw new \InvalidArgumentException(sprintf('Failed to copy %s to %s', $source, $destination));
      }
    }
    return $this;
  }

  /**
   * Checks if file is dist.
   *
   * @param string $file
   *   The file to check.
   *
   * @return bool
   *   Whether the given file is dist or not.
   */
  private function fileIsDist(string $file) : bool {
    return str_starts_with($file, '.dist');
  }

  /**
   * Removes given file or directory from disk.
   *
   * @param string $source
   *   The source file.
   *
   * @return int
   *   The exit code.
   */
  private function removeFile(string $source) : int {
    $command = is_dir($source) ? 'rmdir' : 'rm';

    if (!file_exists($source)) {
      return 0;
    }
    $process = new Process([$command, $source]);
    return $process->run(function ($type, $buffer) {
      $type === Process::ERR ?
        $this->io()->error($buffer) :
        $this->io()->writeln($buffer);
    });
  }

  /**
   * Remove old leftover files.
   *
   * @return $this
   *   The self.
   */
  private function removeFiles(bool $updateDist) : self {
    $map = [
      'docker/local/Dockerfile',
      'docker/local/custom.locations',
      'docker/local/entrypoints/30-chromedriver.sh',
      'docker/local/entrypoints/30-drush-server.sh',
      'docker/local/nginx.conf',
      'docker/local/php-fpm-pool.conf',
      'docker/local/',
      'drush/Commands/OpenShiftCommands.php',
    ];

    foreach ($map as $source) {
      if (!$updateDist && $this->fileIsDist($source)) {
        continue;
      }
      if ($this->removeFile($source) !== DrushCommands::EXIT_SUCCESS) {
        throw new \InvalidArgumentException('Failed to remove file: ' . $source);
      }
    }
    return $this;
  }

  /**
   * Updates files from platform.
   *
   * @param bool[] $options
   *   The options.
   *
   * @command helfi:tools:update-platform
   *
   * @return int
   *   The exit code.
   */
  public function updatePlatform(array $options = ['update-dist' => TRUE]) : int {
    [
      'update-dist' => $updateDist,
    ] = $this->parseOptions($options);

    $this->updateFiles($updateDist)
      ->removeFiles($updateDist);

    return DrushCommands::EXIT_SUCCESS;
  }

}
