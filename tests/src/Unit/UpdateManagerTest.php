<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_drupal_tools\Unit;

use Drupal\Tests\UnitTestCase;
use DrupalTools\HttpFileManager;
use DrupalTools\Update\FileManager;
use DrupalTools\Update\UpdateManager;
use DrupalTools\Update\UpdateOptions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests update manager.
 *
 * @group helfi_drupal_tools
 */
class UpdateManagerTest extends UnitTestCase {

  use ProphecyTrait;

  public function testRun() : void {
    $fileManager = $this->prophesize(Filesystem::class);
    $manager = new UpdateManager(
      $fileManager->reveal(),
      $this->prophesize(FileManager::class)->reveal(),
      __DIR__ . '/../fixtures/schema',
      '\DrupalToolsTest',
    );
    $this->assertEmpty($manager->run(new UpdateOptions(runMigrations: FALSE)));

    $results = $manager->run(new UpdateOptions());
  }
}
