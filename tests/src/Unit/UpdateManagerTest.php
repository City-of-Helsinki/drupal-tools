<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Unit;

use DrupalTools\Update\FileManager;
use DrupalTools\Update\MissingUpdateHookException;
use DrupalTools\Update\UpdateHookManager;
use DrupalTools\Update\UpdateOptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests update manager.
 */
#[Group('helfi_drupal_tools')]
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

  /**
   * Tests that an outdated schema version fails.
   */
  public function testMissingHooks() : void {
    $this->filesystem->dumpFile($this->schemaFile, '2');
    $manager = new UpdateHookManager(
      new Filesystem(),
      $this->prophesize(FileManager::class)->reveal(),
      '\DrupalToolsGapTest',
    );

    $this->expectException(MissingUpdateHookException::class);
    $this->expectExceptionMessage('Update hooks 3-4 have been removed.');

    try {
      $manager->run($this->schemaFile, new UpdateOptions());

    }
    finally {
      // Make sure nothing was run and the schema is untouched.
      $this->assertSame('2', file_get_contents($this->schemaFile));
    }
  }

  /**
   * Tests that a missing schema file fails when old hooks are removed.
   */
  public function testMissingHooksWithoutSchema() : void {
    $this->filesystem->remove($this->schemaFile);
    $manager = new UpdateHookManager(
      new Filesystem(),
      $this->prophesize(FileManager::class)->reveal(),
      '\DrupalToolsGapTest',
    );

    $this->expectException(MissingUpdateHookException::class);
    $this->expectExceptionMessage('Update hooks 1-4 have been removed');
    $manager->run($this->schemaFile, new UpdateOptions());
  }

  /**
   * Tests that hooks are run when the schema is right before the oldest hook.
   */
  public function testNoMissingHooks() : void {
    $manager = new UpdateHookManager(
      new Filesystem(),
      $this->prophesize(FileManager::class)->reveal(),
      '\DrupalToolsGapTest',
    );

    $this->filesystem->dumpFile($this->schemaFile, '4');
    $results = $manager->run($this->schemaFile, new UpdateOptions());
    $this->assertSame(['message 5'], $results[0]->messages);
    $this->assertSame(['message 6'], $results[1]->messages);
    $this->assertSame('6', file_get_contents($this->schemaFile));

    // Up-to-date schema runs nothing and does not fail.
    $this->assertEmpty($manager->run($this->schemaFile, new UpdateOptions()));
    $this->assertSame('6', file_get_contents($this->schemaFile));
  }

}
