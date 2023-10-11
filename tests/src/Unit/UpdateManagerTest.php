<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_drupal_tools\Unit;

use DrupalTools\Update\FileManager;
use DrupalTools\Update\UpdateManager;
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
   * Tests update hooks.
   */
  public function testRun() : void {
    $schemaFile = __DIR__ . '/../fixtures/schema';
    $fileManager = $this->prophesize(Filesystem::class);
    $fileManager->exists($schemaFile)
      ->shouldBeCalled()
      ->willReturn(FALSE);
    $fileManager->dumpFile($schemaFile, 1)
      ->shouldBeCalled();
    $fileManager->dumpFile($schemaFile, 2)
      ->shouldBeCalled();

    $manager = new UpdateManager(
      $fileManager->reveal(),
      $this->prophesize(FileManager::class)->reveal(),
      $schemaFile,
      '\DrupalToolsTest',
    );
    // Make sure no migrations are run when runMigrations is set to FALSE.
    $this->assertEmpty($manager->run(new UpdateOptions(runMigrations: FALSE)));

    $results = $manager->run(new UpdateOptions());

    $found = 0;
    foreach ($results as $result) {
      $this->assertSame(['message ' . $found++], $result->messages);
    }
    $this->assertEquals(2, $found);
  }

}
