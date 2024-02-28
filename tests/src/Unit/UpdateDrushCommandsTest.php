<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Unit;

use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use DrupalTools\Drush\Commands\UpdateDrushCommands;
use DrupalTools\Update\FileManager;
use DrupalTools\Update\UpdateHookManager;
use Drush\Commands\DrushCommands;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests Update drush commands.
 *
 * @todo Improve these.
 *
 * @group helfi_drupal_tools
 */
class UpdateDrushCommandsTest extends TestCase {

  use ProphecyTrait;
  use ApiTestTrait;

  /**
   * Creates a new mock SUT.
   *
   * @param \Symfony\Component\Filesystem\Filesystem|null $filesystem
   *   The filesystem.
   * @param \GuzzleHttp\ClientInterface|null $client
   *   The HTTP client.
   * @param \DrupalTools\Update\FileManager|null $fileManager
   *   The file manager mock.
   * @param \Symfony\Component\Console\Style\OutputStyle|null $output
   *   The output.
   *
   * @return \DrupalTools\Drush\Commands\UpdateDrushCommands
   *   The SUT.
   */
  public function getMockSut(
    ?Filesystem $filesystem = NULL,
    ?ClientInterface $client = NULL,
    ?FileManager $fileManager = NULL,
    ?OutputStyle $output = NULL,
  ) : UpdateDrushCommands {
    if (!$filesystem) {
      $filesystem = $this->prophesize(Filesystem::class)->reveal();
    }
    if (!$client) {
      $client = $this->prophesize(ClientInterface::class)->reveal();
    }
    if (!$fileManager) {
      $fileManager = $this->prophesize(FileManager::class)->reveal();
    }
    if (!$output) {
      $output = $this->prophesize(OutputStyle::class)->reveal();
    }
    return new UpdateDrushCommands(
      $filesystem,
      $client,
      $fileManager,
      new UpdateHookManager($filesystem, $fileManager, '\DrupalToolsTest'),
      $output,
    );
  }

  /**
   * Make sure instance can be initialized via DI.
   */
  public function testCreateEarly() : void {
    $container = new Container();
    $container->add('input', new ArgvInput());
    $container->add('output', new NullOutput());
    $this->assertInstanceOf(UpdateDrushCommands::class, UpdateDrushCommands::createEarly($container));
  }

  /**
   * Tests update without git root.
   */
  public function testEmptyRootException() : void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Failed to parse GIT root.');
    $fs = $this->prophesize(Filesystem::class);
    $fs->exists(Argument::containingString('.git'))
      ->shouldBeCalled()
      ->willReturn(FALSE);
    $sut = $this->getMockSut(filesystem: $fs->reveal());
    $sut->updatePlatform(UpdateDrushCommands::DEFAULT_OPTIONS + ['root' => '.']);
  }

  /**
   * Make sure command fails with proper exit code when root is not defined.
   */
  public function testNoRoot() : void {
    $output = $this->prophesize(OutputStyle::class);
    $output->error('No root provided')->shouldBeCalled();

    $return = $this->getMockSut(output: $output->reveal())->updatePlatform();
    $this->assertEquals(DrushCommands::EXIT_FAILURE, $return);
  }

  /**
   * Tests that package is marked out of date.
   */
  public function testSelfOutOfDate() : void {
    $fs = $this->prophesize(Filesystem::class);
    $fs->exists(Argument::any())->shouldBeCalled()
      ->willReturn(TRUE);
    $output = $this->prophesize(OutputStyle::class);
    $output->error(Argument::containingString('drupal/helfi_drupal_tools is out of date'))->shouldBeCalled();
    $client = $this->createMockHttpClient([
      new Response(body: ''),
    ]);

    $return = $this->getMockSut(filesystem: $fs->reveal(), client: $client, output: $output->reveal())
      ->updatePlatform(array_merge(UpdateDrushCommands::DEFAULT_OPTIONS, [
        'ignore-files' => FALSE,
        'root' => '.',
      ]));
    $this->assertEquals(DrushCommands::EXIT_FAILURE_WITH_CLARITY, $return);
  }

}
