<?php

declare(strict_types = 1);

namespace DrupalTools;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Utils;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The file manager service.
 */
final class FileManager {

  /**
   * Constructs a new instance.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   *   The filesystem.
   * @param array $ignore
   *   An array of filenames to ignore.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly Filesystem $filesystem,
    private readonly array $ignore,
  ) {
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
   * Checks if the given file should be ignored.
   *
   * @param string $file
   *   The file to check.
   *
   * @return bool
   *   TRUE if the file should be ignored.
   */
  private function ignoreFile(string $file) : bool {
    return isset($this->ignore[$file]);
  }

  /**
   * Updates files from Platform.
   *
   * @param \DrupalTools\UpdateOptions $options
   *   The options.
   * @param array $map
   *   A list of files to update.
   *
   * @return $this
   *   The self.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Symfony\Component\Filesystem\Exception\IOException
   */
  public function updateFiles(UpdateOptions $options, array $map) : self {
    foreach ($map as $source => $destination) {
      // Fallback source to destination if the source is not defined.
      if (is_numeric($source)) {
        $source = $destination;
      }

      // Allow files to be ignored.
      if ($options->ignoreFiles && $this->ignoreFile($source)) {
        continue;
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
      // For example, '.github/workflows/test.yml.dist' should not be added
      // again if '.github/workflows/test.yml' exists unless explicitly told so.
      if ($isDist && ($this->filesystem->exists($source) && !$this->filesystem->exists($destination))) {
        $this->copyFile($source, $source);

        continue;
      }

      // Skip updating .dist files if configured so.
      if (!$options->updateDist && $isDist) {
        continue;
      }
      $this->copyFile($source, $destination);
    }
    return $this;
  }

  /**
   * Make sure the destination folder exists.
   *
   * @param string $destination
   *   The destination file.
   *
   * @throws \Symfony\Component\Filesystem\Exception\IOException
   */
  private function ensureFolder(string $destination) : void {
    $parts = explode('/', $destination);
    array_pop($parts);

    if (count($parts) === 0) {
      return;
    }

    $folder = implode('/', $parts);

    $this->filesystem->mkdir($folder, 0755);
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
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Symfony\Component\Filesystem\Exception\IOException
   */
  private function copyFile(string $source, string $destination) : bool {
    $this->ensureFolder($source);
    $resource = Utils::tryFopen($destination, 'w');
    $this->httpClient->request('GET', $source, ['sink' => $resource]);

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
   * Creates the given file with given content.
   *
   * @param string $source
   *   The file.
   * @param string|null $content
   *   The content.
   *
   * @throws \Symfony\Component\Filesystem\Exception\IOException
   */
  private function createFile(string $source, ?string $content = NULL) : void {
    if ($this->filesystem->exists($source)) {
      return;
    }
    $this->filesystem->dumpFile($source, $content);
  }

  /**
   * Remove old leftover files.
   *
   * @param \DrupalTools\UpdateOptions $options
   *   The options.
   * @param array $map
   *   A list of files to remove.
   *
   * @return $this
   *   The self.
   *
   * @throws \Symfony\Component\Filesystem\Exception\IOException
   */
  public function removeFiles(UpdateOptions $options, array $map) : self {
    foreach ($map as $source) {
      if ($options->ignoreFiles && $this->ignoreFile($source)) {
        continue;
      }
      $this->filesystem->remove($source);
    }
    return $this;
  }

  /**
   * Adds the given files.
   *
   * @param \DrupalTools\UpdateOptions $options
   *   The options.
   * @param array $map
   *   A list of files to add.
   *
   * @return $this
   *   The self.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Symfony\Component\Filesystem\Exception\IOException
   */
  public function addFiles(UpdateOptions $options, array $map) : self {
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

      if ($options->ignoreFiles && $this->ignoreFile($source)) {
        continue;
      }
      // Copy remote file to given destination.
      if ($isRemote) {
        $destination = $destination ?? $source;

        // Created files should never be updated.
        if ($this->filesystem->exists($destination)) {
          continue;
        }
        $this->copyFile($source, $destination ?? $source);

        continue;
      }

      $this->createFile($source, $content);
    }
    return $this;
  }

}
