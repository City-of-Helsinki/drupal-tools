<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Unit;

use DrupalTools\HttpFileManager;
use DrupalTools\Update\FileManager;
use DrupalTools\Update\UpdateOptions;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prediction\CallPrediction;
use Prophecy\Prediction\NoCallsPrediction;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests file manager.
 *
 * @group helfi_drupal_tools
 */
class FileManagerTest extends TestCase {

  use ProphecyTrait;

  /**
   * Tests that updateFiles() work with ignoreFiles option.
   *
   * @dataProvider updateFilesIgnoreData
   */
  public function testUpdateFilesIgnore(array $ignoreFiles, array $files) : void {
    $filesystem = $this->prophesize(Filesystem::class);
    $client = $this->prophesize(HttpFileManager::class);

    foreach ($files as $name => $prediction) {
      // Make sure ensureFolder() is called.
      $filesystem->mkdir(dirname($name), 0755)
        ->should($prediction);
      $client->copyFile($name, $name)
        ->should($prediction);
    }

    $options = new UpdateOptions(ignoreFiles: $ignoreFiles);
    $sut = new FileManager($client->reveal(), $filesystem->reveal());

    $sut->updateFiles($options, [
      0 => 'public/sites/default/settings.php',
      'public/Dockerfile' => 'public/Dockerfile',
    ]);
  }

  /**
   * The data provider for testUpdateFilesIgnore().
   *
   * @return array[]
   *   The data.
   */
  public function updateFilesIgnoreData() : array {
    return [
      // Make sure files are ignored when ignoreFiles is not empty.
      [
        ['public/Dockerfile'],
        [
          'public/Dockerfile' => new NoCallsPrediction(),
          'public/sites/default/settings.php' => new CallPrediction(),
        ],
      ],
      // Make sure files are not ignored when ignoreFiles is empty.
      [
        [],
        [
          'public/Dockerfile' => new CallPrediction(),
          'public/sites/default/settings.php' => new CallPrediction(),
        ],
      ],
    ];
  }

  /**
   * Tests updateFiles() with .dist files.
   */
  public function testUpdateFilesIsDist() : void {
    $filesystem = $this->prophesize(Filesystem::class);
    $filesystem->exists('test1.yml')
      ->shouldBeCalled()
      ->willReturn(TRUE);
    $filesystem->exists('test2.yml')
      ->shouldBeCalled()
      ->willReturn(FALSE);

    $client = $this->prophesize(HttpFileManager::class);
    $client->copyFile('test1.yml.dist', 'test1.yml')
      ->shouldBeCalled();
    $client->copyFile('test2.yml.dist', 'test2.yml.dist')
      ->shouldBeCalled();

    $options = new UpdateOptions();
    $sut = new FileManager($client->reveal(), $filesystem->reveal());
    // The test1.yml.dist should be updated as test1.yml because
    // test1.yml already exists.
    $sut->updateFiles($options, ['test1.yml.dist' => 'test1.yml']);

    // The test2.yml.dist should be as test2.yml.dist because
    // the destination file does not exist.
    $sut->updateFiles($options, ['test2.yml.dist' => 'test2.yml']);
  }

  /**
   * Make sure we skip files that cannot be updated.
   */
  public function testUpdateFilesCannotBeUpdated() : void {
    $client = $this->prophesize(HttpFileManager::class);
    $client->copyFile()
      ->shouldNotBeCalled();
    $filesystem = $this->prophesize(Filesystem::class);
    $options = new UpdateOptions(hasWorkFlowAccess: FALSE);
    $sut = new FileManager($client->reveal(), $filesystem->reveal());
    $sut->updateFiles($options, [
      '.github/workflows/test.yml' => '.github/workflows/test.yml',
    ]);
  }

  /**
   * Tests ignoreFiles option with addFiles().
   *
   * @dataProvider addFilesIgnoreFilesData
   */
  public function testAddFilesIgnoreFiles(array $ignoreFiles, array $files) : void {
    $client = $this->prophesize(HttpFileManager::class);
    $filesystem = $this->prophesize(Filesystem::class);

    foreach ($files as $name => $prediction) {
      $filesystem->exists($name)->should($prediction);
      $filesystem->dumpFile($name, '123')->should($prediction);
    }
    $sut = new FileManager($client->reveal(), $filesystem->reveal());
    $sut->addFiles(new UpdateOptions(ignoreFiles: $ignoreFiles), [
      'Dockerfile' => ['content' => '123'],
      'testfile' => ['content' => '123'],
    ]);
  }

  /**
   * Data provider for testAddFilesIgnoreFiles().
   *
   * @return array[]
   *   The data.
   */
  public function addFilesIgnoreFilesData() : array {
    return [
      // Make sure files are ignored when ignoreFiles is not empty.
      [
        ['Dockerfile'],
        [
          'Dockerfile' => new NoCallsPrediction(),
          'testfile' => new CallPrediction(),
        ],
      ],
      // Make sure files are not ignored when ignoreFiles is empty.
      [
        [],
        [
          'Dockerfile' => new CallPrediction(),
          'testfile' => new CallPrediction(),
        ],
      ],
    ];
  }

  /**
   * Tests that existing remote files are not overridden.
   */
  public function testRemoteFile() : void {
    $client = $this->prophesize(HttpFileManager::class);
    $client->copyFile('Dockerfile', 'Dockerfile')
      ->shouldNotBeCalled();
    $client->copyFile('Dockerfile.3', 'Dockerfile.3')
      ->shouldBeCalled();
    $filesystem = $this->prophesize(Filesystem::class);
    $filesystem->exists('Dockerfile')
      ->shouldBeCalled()
      ->willReturn(TRUE);
    $filesystem->exists('Dockerfile.1')
      ->shouldBeCalled()
      ->willReturn(TRUE);
    $filesystem->exists('Dockerfile.3')
      ->shouldBeCalled()
      ->willReturn(FALSE);
    $sut = new FileManager($client->reveal(), $filesystem->reveal());
    // Make ignore files are not set, so we can also make sure ignored
    // files are not taken into account.
    $sut->addFiles(new UpdateOptions(ignoreFiles: []), [
      'Dockerfile' => [
        'remote' => TRUE,
        'destination' => 'Dockerfile.1',
      ],
    ]);
    // Make sure destination fallbacks to source.
    $sut->addFiles(new UpdateOptions(ignoreFiles: []), [
      'Dockerfile' => [
        'remote' => TRUE,
      ],
    ]);
    // Make sure a remote file that does not exist yet is added.
    $sut->addFiles(new UpdateOptions(), [
      'Dockerfile.3' => [
        'remote' => TRUE,
      ],
    ]);
  }

  /**
   * Tests that existing files are not overridden.
   */
  public function testAddFileExisting() : void {
    $client = $this->prophesize(HttpFileManager::class);
    $filesystem = $this->prophesize(Filesystem::class);
    $filesystem->exists('Dockerfile')
      ->shouldBeCalled()
      ->willReturn(TRUE);
    $sut = new FileManager($client->reveal(), $filesystem->reveal());
    $sut->addFiles(new UpdateOptions(), [
      'Dockerfile' => [
        'content' => '123',
        // Make sure setting destination does nothing.
        'destination' => 'Dockerfile.1',
      ],
    ]);
    $sut->addFiles(new UpdateOptions(), [
      'Dockerfile' => [
        'content' => '123',
      ],
    ]);
  }

  /**
   * Make sure we can add new files.
   */
  public function testAddFile() : void {
    $client = $this->prophesize(HttpFileManager::class);
    $filesystem = $this->prophesize(Filesystem::class);
    $filesystem->exists('Dockerfile')
      ->shouldBeCalled()
      ->willReturn(FALSE);
    $filesystem->dumpFile('Dockerfile', '123')
      ->shouldBeCalled();
    $sut = new FileManager($client->reveal(), $filesystem->reveal());
    $sut->addFiles(new UpdateOptions(), [
      'Dockerfile' => [
        'content' => '123',
      ],
    ]);
  }

  /**
   * Tests that removed files work with ignoreFiles option.
   *
   * @dataProvider removeIgnoreFilesData
   */
  public function testRemoveIgnoreFiles(array $ignoreFiles, array $files) : void {
    $client = $this->prophesize(HttpFileManager::class);
    $filesystem = $this->prophesize(Filesystem::class);

    foreach ($files as $name => $prediction) {
      $filesystem->remove($name)->should($prediction);
    }
    $sut = new FileManager($client->reveal(), $filesystem->reveal());
    $sut->removeFiles(new UpdateOptions(ignoreFiles: $ignoreFiles), array_keys($files));
  }

  /**
   * A data provider for testRemoveIgnoreFiles().
   *
   * @return array[]
   *   The data.
   */
  public function removeIgnoreFilesData() : array {
    return [
      // Make sure files are ignored when ignoreFiles is not empty.
      [
        ['Dockerfile'],
        [
          'Dockerfile' => new NoCallsPrediction(),
          'folder/some' => new CallPrediction(),
        ],
      ],
      // Make sure files are not ignored when ignoreFiles is empty.
      [
        [],
        [
          'Dockerfile' => new CallPrediction(),
          'folder/some' => new CallPrediction(),
        ],
      ],
    ];
  }

}
