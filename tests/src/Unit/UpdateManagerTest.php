<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Unit;

use DrupalTools\Update\FileManager;
use DrupalTools\Update\UpdateHookManager;
use DrupalTools\Update\UpdateOptions;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests update manager.
 *
 * @group helfi_drupal_tools
 */
class UpdateManagerTest extends TestCase {

  use ProphecyTrait;

  /**
   * The schema file.
   *
   * @var string
   */
  private string $schemaFile;

  /**
   * The file system.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  private Filesystem $filesystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->schemaFile = __DIR__ . '/../../fixtures/schema';
    $this->filesystem = new Filesystem();
    $this->filesystem->dumpFile($this->schemaFile, '');
  }

  /**
   * Tests the schema with real filesystem.
   */
  public function testSchemaVersion() : void {
    $manager = new UpdateHookManager(
      new Filesystem(),
      $this->prophesize(FileManager::class)->reveal(),
      '\DrupalToolsTest',
    );
    $this->assertTrue($this->filesystem->exists($this->schemaFile));
    $this->assertSame('', file_get_contents($this->schemaFile));

    $manager->run($this->schemaFile, new UpdateOptions());
    $this->assertEquals(2, file_get_contents($this->schemaFile));
  }

  /**
   * Tests update hooks.
   */
  public function testRun() : void {
    $fileManager = $this->prophesize(Filesystem::class);
    $fileManager->exists($this->schemaFile)
      ->shouldBeCalled()
      ->willReturn(FALSE);
    $fileManager->dumpFile($this->schemaFile, 1)
      ->shouldBeCalled();
    $fileManager->dumpFile($this->schemaFile, 2)
      ->shouldBeCalled();

    $manager = new UpdateHookManager(
      $fileManager->reveal(),
      $this->prophesize(FileManager::class)->reveal(),
      '\DrupalToolsTest',
    );
    // Make sure no migrations are run when runMigrations is set to FALSE.
    $this->assertEmpty($manager->run($this->schemaFile, new UpdateOptions(runMigrations: FALSE)));

    $results = $manager->run($this->schemaFile, new UpdateOptions());

    $found = 0;
    foreach ($results as $result) {
      $this->assertSame(['message ' . $found++], $result->messages);
    }
    $this->assertEquals(2, $found);
  }

}
