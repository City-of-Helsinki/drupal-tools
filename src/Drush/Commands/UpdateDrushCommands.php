<?php

declare(strict_types = 1);

namespace DrupalTools\Drush\Commands;

use Composer\InstalledVersions;
use DrupalTools\HttpFileManager;
use DrupalTools\Update\FileManager;
use DrupalTools\Update\UpdateHookManager;
use DrupalTools\Update\UpdateOptions;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * A Drush commandfile.
 */
final class UpdateDrushCommands extends DrushCommands {

  private const BASE_URL = 'https://raw.githubusercontent.com/City-of-Helsinki/drupal-helfi-platform/main/';

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private readonly ClientInterface $httpClient;

  /**
   * The filesystem.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  private readonly Filesystem $filesystem;

  /**
   * The update manager.
   *
   * @var \DrupalTools\Update\UpdateHookManager
   */
  private readonly UpdateHookManager $updateHookManager;

  /**
   * The file manager.
   *
   * @var \DrupalTools\Update\FileManager
   */
  private readonly FileManager $fileManager;

  /**
   * Constructs a new instance.
   */
  public function __construct() {
    parent::__construct();

    $this->filesystem = new Filesystem();
    $this->httpClient = new Client(['base_uri' => self::BASE_URL]);
    $this->fileManager = new FileManager(
      new HttpFileManager($this->httpClient),
      $this->filesystem,
      $this->getFileIgnores()
    );
    $this->updateHookManager = new UpdateHookManager(
      $this->filesystem,
      $this->fileManager,
      sprintf('%s/.platform/schema', $this->gitRoot()),
    );
  }

  /**
   * Gets the files to ignore.
   *
   * @return array
   *   An array of ignored files.
   */
  private function getFileIgnores() : array {
    $ignoreFile = sprintf('%s/.platform/ignore', $this->gitRoot());

    if (!$this->filesystem->exists($ignoreFile)) {
      return [];
    }
    $files = explode(PHP_EOL, file_get_contents($ignoreFile));
    $files = array_filter(array_map('trim', $files));

    return array_combine($files, $files);
  }

  /**
   * Gets the Git root.
   *
   * @return string
   *   The git root.
   */
  private function gitRoot() : string {
    static $gitRoot = NULL;

    if (!$gitRoot) {
      $gitRoot = Path::canonicalize(sprintf('%s/../../../', rtrim(__DIR__, '/')));

      if (!$this->filesystem->exists($gitRoot . '/.git')) {
        throw new \InvalidArgumentException('Failed to parse GIT root.');
      }
    }
    return $gitRoot;
  }

  /**
   * Parses given options.
   *
   * @param array $options
   *   A list of options to parse.
   *
   * @return \DrupalTools\Update\UpdateOptions
   *   The options.
   */
  private function parseOptions(array $options) : UpdateOptions {
    foreach ($options as $key => $value) {
      // Convert (string) true and false to proper booleans.
      // This seems to be fixed in Drush 11, but keep this for BC.
      if (is_string($value) && (strtolower($value) === 'true' || strtolower($value) === 'false')) {
        $options[$key] = strtolower($value) === 'true';
      }
    }

    return new UpdateOptions(
      ignoreFiles: $options['ignore-files'],
      updateExternalPackages: $options['update-external-packages'],
      selfUpdate: $options['self-update'],
      runMigrations: $options['run-migrations'],
    );
  }

  /**
   * Updates the dependencies.
   *
   * @param \DrupalTools\Update\UpdateOptions $options
   *   The update options.
   *
   * @return self
   *    The self.
   */
  private function updateExternalPackages(UpdateOptions $options) : self {
    if (!$options->updateExternalPackages) {
      return $this;
    }
    // Update druidfi/tools only if the package exists.
    if ($this->filesystem->exists($this->gitRoot() . '/tools')) {
      $this->processManager()->process([
        'make',
        'self-update',
      ])->run(function (string $type, ?string $output) : void {
        $this->io()->write($output);
      });
    }
    return $this;
  }

  /**
   * Checks if the self-package needs to be updated.
   *
   * @param \DrupalTools\Update\UpdateOptions $options
   *   The update options.
   *
   * @return bool
   *   TRUE if the package needs to be updated.
   */
  private function needsUpdate(UpdateOptions $options) : bool {
    if (!$options->selfUpdate) {
      return FALSE;
    }
    static $latestCommit = NULL;

    if (!$latestCommit) {
      $body = $this->httpClient
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
   * Runs update hooks.
   *
   * @param \DrupalTools\Update\UpdateOptions $options
   *   The update options.
   *
   * @return self
   *   The self.
   */
  private function runUpdateHooks(UpdateOptions $options) : self {
    $results = $this->updateHookManager->run($options);

    /** @var \DrupalTools\Update\UpdateResult $result */
    foreach ($results as $result) {
      $this->io()
        ->writeln(array_map(fn (mixed $message) => $message,
          $result->messages
        ));
    }

    return $this;
  }

  /**
   * Contains a list of files that should be updated by default.
   *
   * @param \DrupalTools\Update\UpdateOptions $options
   *   The update options.
   *
   * @return self
   *   The self.
   */
  private function updateDefaultFiles(UpdateOptions $options) : self {
    $this->fileManager
      ->updateFiles($options, [
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
      ->updateFiles($options, [
        '.github/workflows/test.yml.dist' => '.github/workflows/test.yml',
        '.github/workflows/artifact.yml.dist' => '.github/workflows/artifact.yml',
        '.github/workflows/update-config.yml.dist' => '.github/workflows/update-config.yml',
        '.gitignore.dist' => '.gitignore',
        '.github/workflows/auto-release-pr.yml.dist' => '.github/workflows/auto-release-pr.yml',
      ]);
    return $this;
  }

  /**
   * Files that should always be added by default.
   *
   * @param \DrupalTools\Update\UpdateOptions $options
   *   The options.
   *
   * @return self
   *   The self.
   */
  private function addDefaultFiles(UpdateOptions $options) : self {
    $this->fileManager->addFiles($options, [
      'docker/openshift/crons/base.sh' => ['remote' => TRUE],
      'public/sites/default/all.settings.php' => ['remote' => TRUE],
    ]);
    return $this;
  }

  /**
   * Updates files from Platform.
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
  public function updatePlatform(array $options = [
    'ignore-files' => TRUE,
    'update-external-packages' => TRUE,
    'self-update' => TRUE,
    'run-migrations' => TRUE,
  ]) : int {
    $options = $this->parseOptions($options);

    if (getenv('CI')) {
      $options->isCI = TRUE;
    }
    // Make sure all operations are relative to Git root.
    chdir($this->gitRoot());

    if ($this->needsUpdate($options)) {
      $this->io()->writeln('<comment>drupal/helfi_drupal_tools is out of date. Please run "composer update drupal/helfi_drupal_tools" to update it and re-run this command.</comment>');

      return DrushCommands::EXIT_SUCCESS;
    }
    $this->updateExternalPackages($options)
      ->runUpdateHooks($options)
      ->updateDefaultFiles($options)
      ->addDefaultFiles($options);

    return DrushCommands::EXIT_SUCCESS;
  }

}
