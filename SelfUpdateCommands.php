<?php

declare(strict_types = 1);

namespace Drush\Commands\helfi_drupal_tools;

use Composer\InstalledVersions;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use DrupalTools\UpdateManager;
use Drush\Utils\StringUtils;
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
   * @var null|\GuzzleHttp\ClientInterface
   */
  private ?ClientInterface $httpClient = NULL;

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

  private function updateManager() : UpdateManager {
    return new UpdateManager();
  }

  /**
   * Parses given options.
   *
   * @param array $options
   *   A list of options to parse.
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
   * Updates individual files from platform.
   *
   * @param array $files
   *   A comma delimited list of files to update.
   * @param array $options
   *   An array of options.
   *
   * @command helfi:tools:update-platform-files
   *
   * @return int
   *   The exit code.
   */
  #[Command(name: 'helfi:tools:update-platform-files')]
  public function updatePlatformFiles(array $files, array $options = [
    'update-dist' => TRUE,
  ]) : int {
    if (count($files) < 1) {
      throw new \InvalidArgumentException('You must provide at least one file.');
    }
    $files = StringUtils::csvToArray($files);

    [
      'update-dist' => $updateDist,
    ] = $this->parseOptions($options);

    foreach ($files as $file) {
      [$source, $destination] = explode('=', $file) + [NULL, NULL];

      $destination ? $this->updateFiles($updateDist, [$source => $destination]) :
        $this->updateFiles($updateDist, [0 => $source]);
    }

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Updates the dependencies.
   *
   * @param array $options
   *   The options.
   */
  private function updateExternalPackages(array $options) : void {
    if (empty($options['root'])) {
      throw new \InvalidArgumentException('Missing drupal root.');
    }
    $gitRoot = sprintf('%s/..', rtrim($options['root'], '/'));

    // Update druidfi/tools if the package exists.
    if (is_dir($gitRoot . '/tools')) {
      $this->processManager()->process([
        'make',
        'self-update',
      ])->run(function (string $type, ?string $output) : void {
        $this->io()->write($output);
      });
    }
  }

  private function needsUpdate() : bool {
    static $latestCommit = NULL;

    if (!$latestCommit) {
      $body = $this->httpClient()
        ->request('GET', 'https://api.github.com/repos/city-of-helsinki/drupal-tools/commits/main')
        ?->getBody()
        ?->getContents();

      $latestCommit = $body ? json_decode($body)?->sha : '';
    }

    $selfVersion = InstalledVersions::getReference('drupal/helfi_drupal_tools');

    if (!$latestCommit || !$selfVersion) {
      return TRUE;
    }
    return $selfVersion !== $latestCommit;
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
  #[Command(name: 'helfi:tools:update-platform')]
  public function updatePlatform(array $options = ['update-dist' => TRUE]) : int {
    [
      'update-dist' => $updateDist,
    ] = $this->parseOptions($options);

    $this->updateManager()->run();
    return 0;

    if ($this->needsUpdate()) {
      $this->io()->writeln('<comment>drupal/helfi_drupal_tools is out of date. Run "composer update drupal/helfi_drupal_tools" to update it and re-run this command.</comment>');
    }
    $this->updateExternalPackages($options);

    $this
      ->updateFiles($updateDist, [
        '.github/workflows/test.yml.dist' => '.github/workflows/test.yml',
        '.github/workflows/artifact.yml.dist' => '.github/workflows/artifact.yml',
        '.github/workflows/update-config.yml.dist' => '.github/workflows/update-config.yml',
        '.gitignore.dist' => '.gitignore',
        '.github/workflows/auto-release-pr.yml.dist' => '.github/workflows/auto-release-pr.yml',
      ])
      ->updateFiles($updateDist, [
        'public/sites/default/azure.settings.php',
        'public/sites/default/settings.php',
        'docker/openshift/custom.locations',
        'docker/openshift/Dockerfile',
        'docker/openshift/entrypoints/20-deploy.sh',
        'docker/openshift/crons/content-scheduler.sh',
        'docker/openshift/crons/migrate-status.php',
        'docker/openshift/crons/migrate-tpr.sh',
        'docker/openshift/crons/prestop-hook.sh',
        'docker/openshift/crons/purge-queue.sh',
        'docker/openshift/crons/update-translations.sh',
        'docker/openshift/crons/pubsub.sh',
        'docker/openshift/notify.php',
        'docker-compose.yml',
        'phpunit.xml.dist',
        'phpunit.platform.xml',
        'tools/make/project/install.mk',
        'tools/make/project/git.mk',
        'tools/make/project/theme.mk',
        'tools/commit-msg',
        '.sonarcloud.properties',
        '.github/pull_request_template.md',
        'tests/dtt/src/ExistingSite/ModuleEnabledTest.php',
      ])
      ->removeFiles([
        'docker/local/Dockerfile',
        'docker/openshift/crons/drupal.sh',
        'docker/local/custom.locations',
        'docker/local/entrypoints/30-chromedriver.sh',
        'docker/local/entrypoints/30-drush-server.sh',
        'docker/local/nginx.conf',
        'docker/local/php-fpm-pool.conf',
        'docker/local/',
        'drush/Commands/OpenShiftCommands.php',
      ])
      ->addFiles([
        'docker/openshift/crons/base.sh' => ['remote' => TRUE],
        'public/sites/default/all.settings.php' => ['remote' => TRUE],
      ]);

    return DrushCommands::EXIT_SUCCESS;
  }

}
