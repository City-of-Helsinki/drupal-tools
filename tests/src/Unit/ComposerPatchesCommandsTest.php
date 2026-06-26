<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Unit;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use DrupalTools\Drush\Commands\ComposerPatchesCommands;
use DrupalTools\Package\Exception\VersionCheckException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\Container\Container;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests Composer patches Drush commands.
 */
#[Group('helfi_drupal_tools')]
class ComposerPatchesCommandsTest extends TestCase {

  use ProphecyTrait;
  use ApiTestTrait;

  /**
   * Tests ::create().
   */
  #[Test]
  public function testCreate(): void {
    $this->expectNotToPerformAssertions();
    $container = new Container();
    $container->add('formatterManager', new FormatterManager());
    ComposerPatchesCommands::create($container);
  }

  /**
   * Tests file not found error.
   */
  #[Test]
  public function testComposerLockFound(): void {
    $this->expectException(VersionCheckException::class);
    $client = $this->prophesize(ClientInterface::class);
    $sut = new ComposerPatchesCommands($client->reveal());
    $sut->execute('nonexistent');
  }

  /**
   * Make sure command fails if no valid 'composer.lock' is found.
   */
  #[Test]
  public function testInvalidJson(): void {
    $this->expectException(\JsonException::class);
    $client = $this->prophesize(ClientInterface::class);
    $sut = new ComposerPatchesCommands($client->reveal());
    $sut->execute(__DIR__ . '/../../fixtures/invalid.lock');
  }

  /**
   * Tests working 'composer.lock'.
   */
  #[Test]
  public function testValidJson(): void {
    $client = $this->createMockHttpClient([
      new Response(200),
    ]);
    $sut = new ComposerPatchesCommands($client);
    $result = $sut->execute(__DIR__ . '/../../fixtures/composer.lock');
    $this->assertEquals(ComposerPatchesCommands::EXIT_SUCCESS, $result->getExitCode());
    $this->assertInstanceOf(RowsOfFields::class, $result->getOutputData());

    $this->assertEquals([
      [
        'package' => 'drupal/helfi_api_base',
        'description' => '',
        'patch' => '',
      ],
      [
        'package' => 'drupal/big_pipe_sessionless',
        'description' => 'Drupal 11 support (https://drupal.org/i/3428235)',
        'patch' => 'https://www.drupal.org/files/issues/2025-05-07/3428235.d11.patch',
      ],
    ], (array) $result->getOutputData());
  }

  /**
   * Tests that only allowed packages can add patches.
   */
  #[Test]
  public function testInvalidPackage(): void {
    $client = $this->createMockHttpClient([
      new Response(404),
    ]);
    $sut = new ComposerPatchesCommands($client);
    $result = $sut->execute(__DIR__ . '/../../fixtures/composer.lock');
    $this->assertEquals(ComposerPatchesCommands::EXIT_FAILURE_WITH_CLARITY, $result->getExitCode());
    $this->assertInstanceOf(RowsOfFields::class, $result->getOutputData());

    $this->assertEquals([
      [
        'package' => 'drupal/helfi_api_base',
        'description' => 'This package is not allowed to add patches.',
        'patch' => '',
      ],
    ], (array) $result->getOutputData());
  }

}
