<?php

declare(strict_types = 1);

namespace DrupalTools;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final class FileManager {

  public function __construct(private readonly SymfonyStyle $io) {
  }

  /**
   * Checks if file can be updated.
   *
   * @param string $file
   *   The file.
   *
   * @return bool
   *   TRUE if file can be updated automatically.
   */
  private function fileCanBeUpdated(string $file) : bool {
    $isCI = getenv('CI');

    if ($isCI) {
      // Workflows cannot be updated in CI.
      return !str_starts_with($file, '.github/workflows');
    }
    return TRUE;
  }

  /**
   * Updates files from platform.
   *
   * @param bool $updateDist
   *   Whether to update dist files or not.
   * @param array $map
   *   A list of files to update.
   *
   * @return $this
   *   The self.
   */
  private function updateFiles(bool $updateDist, array $map) : self {
    foreach ($map as $source => $destination) {
      // Fallback source to destination if source is not defined.
      if (is_numeric($source)) {
        $source = $destination;
      }
      // Check if we can update given file. For example, we can't
      // update GitHub workflow files in CI with our current GITHUB_TOKEN.
      // @todo Remove this once we use token with more permissions.
      if (!$this->fileCanBeUpdated($source)) {
        continue;
      }
      $isDist = $this->fileIsDist($source);

      // Update the given dist file only if the original (.dist) file exists and
      // the destination one does not.
      // For example: '.github/workflows/test.yml.dist' should not be added
      // again if '.github/workflows/test.yml' exists unless explicitly told so.
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
   * Make sure the destination folder exists.
   *
   * @param string $destination
   *   The destination file.
   */
  private function ensureFolder(string $destination) : void {
    $parts = explode('/', $destination);
    array_pop($parts);

    if (count($parts) === 0) {
      return;
    }

    $folder = implode('/', $parts);

    if (!is_dir($folder)) {
      if (!mkdir($folder, 0755, TRUE)) {
        throw new \InvalidArgumentException('Failed to create folder: ' . $folder);
      }
    }
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
      $this->ensureFolder($destination);
      $resource = Utils::tryFopen($destination, 'w');
      $this->httpClient()->request('GET', $source, ['sink' => $resource]);
    }
    catch (GuzzleException | \InvalidArgumentException $e) {
      $this->io()->error($e->getMessage());
      return FALSE;
    }
    return TRUE;
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
    return str_ends_with($file, '.dist');
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
   * Creates the given file with given content.
   *
   * @param string $source
   *   The file.
   * @param string|null $content
   *   The content.
   *
   * @return bool
   *   TRUE if file was created, false if not.
   */
  private function createFile(string $source, ?string $content = NULL) : bool {
    if (file_exists($source)) {
      return TRUE;
    }
    return file_put_contents($source, $content) !== FALSE;
  }

  /**
   * Remove old leftover files.
   *
   * @param array $map
   *   A list of files to remove.
   *
   * @return $this
   *   The self.
   */
  private function removeFiles(array $map) : self {
    foreach ($map as $source) {
      if ($this->removeFile($source) !== DrushCommands::EXIT_SUCCESS) {
        throw new \InvalidArgumentException('Failed to remove file: ' . $source);
      }
    }
    return $this;
  }

  /**
   * Adds the given files.
   *
   * @param array $map
   *   A list of files to add.
   *
   * @return $this
   *   The self.
   */
  private function addFiles(array $map) : self {
    foreach ($map as $source => $settings) {
      [
        'remote' => $isRemote,
        'content' => $content,
        'destination' => $destination,
      ] = $settings + [
        'remote' => FALSE,
        'content' => NULL,
        'destination' => NULL,
      ];

      // Copy remote file to given destination.
      if ($isRemote) {
        $destination = $destination ?? $source;

        // Created files should never be updated.
        if (file_exists($destination)) {
          continue;
        }
        $this->copyFile($source, $destination ?? $source);

        continue;
      }

      if (!$this->createFile($source, $content)) {
        throw new \InvalidArgumentException('Failed to create file: ' . $source);
      }
    }
    return $this;
  }

}
