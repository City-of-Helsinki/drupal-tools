<?php

declare(strict_types = 1);

namespace DrupalTools\Drush\Commands;

use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use Symfony\Component\Process\Process;

/**
 * A Drush commandfile.
 */
final class OpenShiftDrushCommands extends DrushCommands {

  /**
   * Make sure env variables are exposed.
   *
   * @return $this
   *   The self.
   */
  private function ensureEnvVariables() : self {
    static $envVariablesSet;

    if ($envVariablesSet) {
      return $this;
    }
    $envVariablesSet = TRUE;
    $envFile = DRUPAL_ROOT . '/../.env';

    if (file_exists($envFile)) {
      $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
          continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_ENV)) {
          putenv(sprintf('%s=%s', $name, $value));
          $_ENV[$name] = $value;
        }
      }
    }
    return $this;
  }

  /**
   * Ensure that we're logged in.
   */
  private function ensureLoginDetails(string $token = NULL) : self {
    static $attemptedLogin;

    if ($attemptedLogin) {
      return $this;
    }
    $attemptedLogin = TRUE;

    try {
      $this->invokeOc(['whoami'], showOutput: FALSE);
    }
    catch (\Exception $e) {
      if ($token === NULL) {
        $token = $this->ask('You must obtain an API token by visiting https://oauth-openshift.apps.arodevtest.hel.fi/oauth/token/request (Token):');
      }
      $this->invokeOc([
        'login',
        '--token=' . $token,
        '--server=https://api.arodevtest.hel.fi:6443',
        '--insecure-skip-tls-verify',
      ]);
    }

    return $this;
  }

  /**
   * Make sure we have project selected.
   *
   * @return $this
   *   The self.
   */
  private function ensureProject() : self {
    static $projectChanged;

    if (!$project = getenv('OC_PROJECT_NAME')) {
      throw new \Exception(dt('OC_PROJECT_NAME env variable is not set.'));
    }

    if ($projectChanged) {
      return $this;
    }
    $projectChanged = TRUE;

    $this->invokeOc(['project', $project]);
    return $this;
  }

  /**
   * Runs oc command with given arguments.
   *
   * @param array $command
   *   The commands to run.
   * @param callable|null $callback
   *   The callback.
   * @param bool $showOutput
   *   Whether to show output or not.
   *
   * @return int
   *   The exit code.
   */
  private function invokeOc(
    array $command,
    ?callable $callback = NULL,
    bool $showOutput = TRUE
  ) : int {
    $this
      ->ensureEnvVariables()
      ->ensureLoginDetails()
      ->ensureProject();

    $fullCommand = 'oc ' . implode(' ', $command);
    $this->io()->note('Running: ' . $fullCommand);
    $process = new Process(['oc', ...$command], timeout: 3600);
    $process->start();

    if (!$callback && $showOutput) {
      $process->wait(function ($type, $buffer) {
        $this->io()->write($buffer);
      });
    }

    if ($callback) {
      $process->wait();
      $callback($process->getOutput());
    }

    if ($process->getExitCode() > self::EXIT_SUCCESS) {
      throw new \Exception(dt('Command ' . $fullCommand . ' failed. See above for details.'));
    }
    return self::EXIT_SUCCESS;
  }

  /**
   * Finds the drupal pod name.
   *
   * @param array $items
   *   The items to scan from.
   *
   * @return string
   *   The pod name.
   */
  private function getDrupalPodName(array $items) : string {
    $deploymentConfigNames = [
      getenv('OC_DEPLOYMENT_CONFIG_NAME') ?: '',
      'drupal-cron',
      'drupal',
    ];

    foreach ($deploymentConfigNames as $name) {
      foreach ($items as $item) {
        if (!isset($item->metadata->labels->deploymentconfig)) {
          continue;
        }
        if ((!isset($item->status->phase)) || $item->status->phase !== 'Running') {
          continue;
        }
        if ($item->metadata->labels->deploymentconfig === $name) {
          return $item->metadata->name;
        }
      }
    }
    throw new \InvalidArgumentException(dt('No running pod found.'));
  }

  /**
   * Gets the database dump.
   *
   * @return int
   *   The exit code.
   */
  #[Command(name: 'helfi:oc:get-dump')]
  public function getDatabaseDump() : int {
    $this->invokeOc(['get', 'pods', '-o', 'json'], callback: function ($output) {
      $data = json_decode($output);
      $pod = $this->getDrupalPodName($data->items);

      $this->invokeOc([
        'rsh',
        $pod,
        'drush',
        'sql:dump',
        '--structure-tables-key=common',
        '--extra-dump="--no-tablespaces"',
        '--result-file=/tmp/dump.sql',
      ]);
      $this->invokeOc([
        'rsync',
        sprintf('%s:/tmp/dump.sql', $pod),
        DRUPAL_ROOT . '/..',
      ]);
    });
    return self::EXIT_SUCCESS;
  }

  /**
   * Sanitizes the current database.
   *
   * @return int
   *   The exit code.
   */
  #[Command(name: 'helfi:oc:sanitize-database')]
  public function sanitizeDatabase() : int {
    $process = $this->processManager()->process([
      'drush',
      'sql-query',
      "UPDATE file_managed SET uri = REPLACE(uri, 'azure://', 'public://')",
    ]);
    $process->run(function ($type, $output) {
      $this->io()->write($output);
    });
    return self::EXIT_SUCCESS;
  }

  /**
   * The OC login command.
   *
   * @param string $token
   *   The token.
   *
   * @return int
   *   The exit code.
   */
  #[Command(name: 'helfi:oc:login')]
  public function login(string $token) : int {
    $this->ensureLoginDetails($token);

    return self::EXIT_SUCCESS;
  }

  /**
   * Checks whether user is logged in or not.
   *
   * @return int
   *   The exit code.
   */
  #[Command(name: 'helfi:oc:whoami')]
  public function whoami() : int {
    try {
      $this->invokeOc(['whoami']);
    }
    catch (\Exception) {
      return self::EXIT_FAILURE;
    }
    return self::EXIT_SUCCESS;
  }

}
