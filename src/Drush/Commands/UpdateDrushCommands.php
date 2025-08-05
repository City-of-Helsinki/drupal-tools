<?php

declare(strict_types=1);

namespace DrupalTools\Drush\Commands;

use Composer\InstalledVersions;
use DrupalTools\HttpFileManager;
use DrupalTools\Update\FileManager;
use DrupalTools\Update\UpdateHookManager;
use DrupalTools\Update\UpdateOptions;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\Command;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * A Drush command to update platform files from upstream.
 */
#[Bootstrap(DrupalBootLevels::NONE)]
final class UpdateDrushCommands extends DrushCommands {

  private const BASE_URL = 'https://raw.githubusercontent.com/City-of-Helsinki/drupal-helfi-platform/';

  /**
   * The git root.
   *
   * @var null|string
   */
  private ?string $gitRoot = NULL;

  public const DEFAULT_OPTIONS = [
    'branch' => 'main',
    'ignore-files' => TRUE,
    'update-external-packages' => TRUE,
    'self-update' => TRUE,
    'run-migrations' => TRUE,
  ];

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly Filesystem $filesystem,
    private readonly ClientInterface $httpClient,
    private readonly FileManager $fileManager,
    private readonly UpdateHookManager $updateHookManager,
    private readonly OutputStyle $style,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $client = new Client(['base_uri' => self::BASE_URL]);
    $fileSystem = new Filesystem();
    $fileManager = new FileManager(new HttpFileManager($client), $fileSystem);

    return new self(
      $fileSystem,
      $client,
      $fileManager,
      new UpdateHookManager($fileSystem, $fileManager),
      new SymfonyStyle($container->get('input'), $container->get('output')),
    );
  }

  /**
   * Gets the Git root.
   *
   * @return string
   *   The git root.
   */
  private function gitRoot(string $root) : string {
    if (!$this->gitRoot) {
      $this->gitRoot = Path::canonicalize(sprintf('%s/../', rtrim($root, '/')));

      if (!$this->filesystem->exists($this->gitRoot . '/.git')) {
        throw new \InvalidArgumentException('Failed to parse GIT root.');
      }
    }
    return $this->gitRoot;
  }

  /**
   * Parses given options.
   *
   * @param array $options
   *   A list of options to parse.
   * @param string $root
   *   The root directory.
   *
   * @return \DrupalTools\Update\UpdateOptions
   *   The options.
   */
  private function parseOptions(array $options, string $root) : UpdateOptions {
    foreach ($options as $key => $value) {
      // Convert (string) true and false to proper booleans.
      // This seems to be fixed in Drush 11, but keep this for BC.
      if (is_string($value) && (strtolower($value) === 'true' || strtolower($value) === 'false')) {
        $options[$key] = strtolower($value) === 'true';
      }
    }

    $ignoreFiles = [];
    if ($options['ignore-files']) {
      $ignoreFile = sprintf('%s/.platform/ignore', $root);

      if ($this->filesystem->exists($ignoreFile)) {
        $files = explode(PHP_EOL, file_get_contents($ignoreFile));
        $files = array_filter(array_map('trim', $files));

        $ignoreFiles = array_combine($files, $files);
      }
    }

    return new UpdateOptions(
      ignoreFiles: $ignoreFiles,
      branch: $options['branch'],
      selfUpdate: $options['self-update'],
      runMigrations: $options['run-migrations'],
    );
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
    $body = $this->httpClient
      ->request('GET', 'https://api.github.com/repos/city-of-helsinki/drupal-tools/commits/main')
      ->getBody()
      ->getContents();

    $latestCommit = $body ? json_decode($body)?->sha : '';
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
   * @param string $root
   *   The root directory.
   *
   * @return self
   *   The self.
   */
  private function runUpdateHooks(UpdateOptions $options, string $root) : self {
    $this->style->note('Running update hooks ...');

    $schemaFile = sprintf('%s/.platform/schema', $root);
    $results = $this->updateHookManager->run($schemaFile, $options);

    /** @var \DrupalTools\Update\UpdateResult $result */
    foreach ($results as $result) {
      $this->style
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
    $this->style->note('Checking files ...');
    $this->fileManager
      ->updateFiles($options, [
        'public/sites/default/azure.settings.php',
        'public/sites/default/settings.php',
        'docker/elastic-proxy/elastic.conf',
        'docker/elastic-proxy/nginx.conf',
        'docker/openshift/Dockerfile',
        'docker/openshift/init.sh',
        'docker/openshift/deploy/10-preflight.sh',
        'docker/openshift/deploy/20-deploy.sh',
        'docker/openshift/entrypoints/10-preflight.sh',
        'docker/openshift/entrypoints/15-twig-compile.sh',
        'docker/openshift/crons/content-scheduler.sh',
        'docker/openshift/crons/migrate-tpr.sh',
        'docker/openshift/crons/prestop-hook.sh',
        'docker/openshift/crons/purge-queue.sh',
        'docker/openshift/crons/update-translations.sh',
        'docker/openshift/crons/pubsub.sh',
        'docker/openshift/crons/cron.sh',
        'docker/openshift/crons/revision-queue.sh',
        'docker/openshift/crons/menu-queue.sh',
        'docker/openshift/post-db-replace/10-prepare.sh',
        'docker/openshift/post-db-replace/50-sanitize.sh',
        'docker/openshift/post-db-replace/90-translations.sh',
        'docker/openshift/preflight/preflight.php',
        'docker/openshift/notify.php',
        '.dockerignore',
        'compose.ci.yaml',
        'compose.yaml',
        'phpcs.xml.dist',
        'phpunit.xml.dist',
        'phpunit.platform.xml',
        'Makefile',
        'tools/make/composer.mk',
        'tools/make/docker.mk',
        'tools/make/drupal.mk',
        'tools/make/git.mk',
        'tools/make/qa.mk',
        'tools/make/theme.mk',
        'tools/commit-msg',
        '.sonarcloud.properties',
        '.github/pull_request_template.md',
        'tests/dtt/src/ExistingSite/ModuleEnabledTest.php',
      ])
      ->updateFiles($options, [
        '.github/dependabot.yml.dist' => '.github/dependabot.yml',
        '.github/workflows/test.yml.dist' => '.github/workflows/test.yml',
        '.github/workflows/artifact.yml.dist' => '.github/workflows/artifact.yml',
        '.github/workflows/update-config.yml.dist' => '.github/workflows/update-config.yml',
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
      'public/sites/default/all.settings.php' => ['remote' => TRUE],
      'phpstan.neon' => ['remote' => TRUE],
    ]);
    return $this;
  }

  /**
   * Updates files from Platform.
   *
   * @param array $options
   *   The options.
   *
   * @command helfi:tools:update-platform
   *
   * @return int
   *   The exit code.
   */
  #[Command(name: 'helfi:tools:update-platform')]
  public function updatePlatform(array $options = self::DEFAULT_OPTIONS) : int {
    if (empty($options['root'])) {
      $this->style->error('No root provided');
      return DrushCommands::EXIT_FAILURE;
    }
    $root = $this->gitRoot($options['root']);
    $options = $this->parseOptions($options, $root);

    if (getenv('CI')) {
      $options->isCI = TRUE;
    }
    // Make sure all operations are relative to Git root.
    chdir($root);

    if ($this->needsUpdate($options)) {
      $this->style->error('drupal/helfi_drupal_tools is out of date. Please run "composer update drupal/helfi_drupal_tools" to update it and re-run this command.');

      return DrushCommands::EXIT_FAILURE_WITH_CLARITY;
    }
    $this
      ->runUpdateHooks($options, $root)
      ->updateDefaultFiles($options)
      ->addDefaultFiles($options);

    return DrushCommands::EXIT_SUCCESS;
  }

}
