<?php

declare(strict_types=1);

namespace DrupalTools\Update;

use DrupalTools\HttpFileManager;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The file manager service.
 */
class FileManager {

  /**
   * Constructs a new instance.
   *
   * @param \DrupalTools\HttpFileManager $httpFileManager
   *   The HTTP file manager service.
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   *   The filesystem.
   */
  public function __construct(
    private readonly HttpFileManager $httpFileManager,
    private readonly Filesystem $filesystem,
  ) {
  }

  /**
   * Checks if file can be updated.
   *
   * @param string $file
   *   The file.
   * @param \DrupalTools\Update\UpdateOptions $options
   *   The update options.
   *
   * @return bool
   *   TRUE if file can be updated automatically.
   */
  private function fileCanBeUpdated(string $file, UpdateOptions $options) : bool {
    if ($options->hasWorkFlowAccess) {
      // Workflows cannot be updated without PAT with 'workflow' access.
      return !str_starts_with($file, '.github/workflows');
    }
    return TRUE;
  }

  /**
   * Checks if the given file should be ignored.
   *
   * @param string $file
   *   The file to check.
   * @param array $ignoredFiles
   *   The files to ignore.
   *
   * @return bool
   *   TRUE if the file should be ignored.
   */
  private function ignoreFile(string $file, array $ignoredFiles) : bool {
    return in_array($file, $ignoredFiles);
  }

  /**
   * Updates files from Platform.
   *
   * @param \DrupalTools\Update\UpdateOptions $options
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
      if ($this->ignoreFile($source, $options->ignoreFiles)) {
        continue;
      }

      // Check if we can update given file. For example, we can't
      // update GitHub workflow files in GitHub automation without Personal
      // Access Token with 'workflow' access.
      if (!$this->fileCanBeUpdated($source, $options)) {
        continue;
      }

      if ($this->fileIsDist($source)) {
        // .dist files require special handling because they are features
        // that are usually disabled by default. For example, we update
        // test.yml.dist like this: $map = ['test.yml.dist' => 'test.yml'],
        // meaning test.yml.dist file will be updated as test.yml.
        if (!$this->filesystem->exists($destination)) {
          // Update the actual dist file if the destination does not exist.
          $destination = $source;
        }
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
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Symfony\Component\Filesystem\Exception\IOException
   */
  private function copyFile(string $source, string $destination) : void {
    $this->ensureFolder($source);
    $this->httpFileManager->copyFile($source, $destination);
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
   * @param \DrupalTools\Update\UpdateOptions $options
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
      if ($this->ignoreFile($source, $options->ignoreFiles)) {
        continue;
      }
      $this->filesystem->remove($source);
    }
    return $this;
  }

  /**
   * Adds the given files.
   *
   * @param \DrupalTools\Update\UpdateOptions $options
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

      if ($this->ignoreFile($source, $options->ignoreFiles)) {
        continue;
      }
      // Copy remote file to given destination.
      if ($isRemote) {
        $destination = $destination ?? $source;

        // Created files should never be updated.
        if ($this->filesystem->exists($destination)) {
          continue;
        }
        $this->copyFile($source, $destination);

        continue;
      }

      $this->createFile($source, $content);
    }
    return $this;
  }

}
