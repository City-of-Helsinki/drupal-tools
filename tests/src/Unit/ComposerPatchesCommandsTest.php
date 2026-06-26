<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Unit;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use DrupalTools\Drush\Commands\ComposerPatchesCommand;
use DrupalTools\Package\Exception\VersionCheckException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\Container\Container;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests Composer patches Drush commands.
 */
#[Group('helfi_drupal_tools')]
class ComposerPatchesCommandsTest extends TestCase {

  use ProphecyTrait;
  use ApiTestTrait;

  /**
   * Gets the SUT.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   *
   * @return \DrupalTools\Drush\Commands\ComposerPatchesCommand
   *   The SUT.
   */
  public function getSut(ClientInterface $client) : ComposerPatchesCommand {
    $container = new Container();
    $container->add(FormatterManager::class, new FormatterManager());
    $container->add(ClientInterface::class, $client);
    return ComposerPatchesCommand::create($container);
  }

  /**
   * Tests empty composer.lock path.
   */
  #[Test]
  public function testEmptyPath(): void {
    $this->expectException(VersionCheckException::class);
    $client = $this->prophesize(ClientInterface::class)->reveal();
    $sut = $this->getSut($client);
    $input = $this->prophesize(InputInterface::class);
    $sut->execute($input->reveal(), $this->prophesize(OutputInterface::class)->reveal());
  }

  /**
   * Tests file not found error.
   */
  #[Test]
  public function testComposerLockFound(): void {
    $this->expectException(VersionCheckException::class);
    $client = $this->prophesize(ClientInterface::class)->reveal();
    $sut = $this->getSut($client);
    $input = $this->prophesize(InputInterface::class);
    $input->getArgument('file')
      ->willReturn('nonexistent');
    $sut->execute($input->reveal(), $this->prophesize(OutputInterface::class)->reveal());
  }

  /**
   * Make sure command fails if no valid 'composer.lock' is found.
   */
  #[Test]
  public function testInvalidJson(): void {
    $this->expectException(\JsonException::class);
    $client = $this->prophesize(ClientInterface::class);
    $sut = $this->getSut($client->reveal());
    $sut->doExecute(__DIR__ . '/../../fixtures/invalid.lock');
  }

  /**
   * Tests working 'composer.lock'.
   */
  #[Test]
  public function testValidJson(): void {
    $client = $this->createMockHttpClient([
      new Response(200),
    ]);
    $sut = $this->getSut($client);
    $result = $sut->doExecute(__DIR__ . '/../../fixtures/composer.lock');
    $this->assertEquals(ComposerPatchesCommand::SUCCESS, $result->getExitCode());
    $this->assertInstanceOf(RowsOfFields::class, $result->getOutputData());

    $this->assertEquals([
      [
        'package' => '<info>drupal/helfi_api_base</info>',
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
    $sut = $this->getSut($client);
    $result = $sut->doExecute(__DIR__ . '/../../fixtures/composer.lock');
    $this->assertEquals(ComposerPatchesCommand::FAILURE, $result->getExitCode());
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
