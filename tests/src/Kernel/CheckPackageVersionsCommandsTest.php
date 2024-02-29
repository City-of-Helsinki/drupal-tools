<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Kernel;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use DrupalTools\Drush\Commands\PackageScannerDrushCommands;
use DrupalTools\OutputFormatters\MarkdownTableFormatter;
use Drush\Commands\DrushCommands;
use Drush\Formatters\DrushFormatterManager;
use GuzzleHttp\Psr7\Response;
use League\Container\Container;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;

/**
 * Tests Package version checker commands.
 *
 * @group helfi_drupal_tools
 */
final class CheckPackageVersionsCommandsTest extends KernelTestBase {

  use ApiTestTrait;
  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['helfi_api_base'];

  /**
   * Initialized a dummy Drush container.
   *
   * @return \Psr\Container\ContainerInterface
   *   The Drush container.
   */
  private function getDrushContainer() : ContainerInterface {
    $container = new Container();
    $container->add('formatterManager', new DrushFormatterManager());
    return $container;
  }

  /**
   * Tests that Markdown table formatter is registered.
   */
  public function testMarkdownTableFormatter() : void {
    $container = $this->getDrushContainer();
    PackageScannerDrushCommands::create($this->container, $container);
    $this->assertInstanceOf(MarkdownTableFormatter::class, $container->get('formatterManager')->getFormatter('markdown_table'));
  }

  /**
   * Tests version check with invalid composer.json file.
   */
  public function testInvalidComposerFileException() : void {
    $sut = PackageScannerDrushCommands::create($this->container, $this->getDrushContainer());
    $this->expectException(\RuntimeException::class);
    $sut->checkVersions('nonexistent.lock');
  }

  /**
   * Tests version check.
   */
  public function testVersionCheck() : void {
    $this->setupMockHttpClient([
      new Response(body: json_encode([
        'packages' => [
          'drupal/helfi_api_base' => [
            [
              'name' => 'drupal/helfi_api_base',
              'version' => '1.1.0',
            ],
          ],
        ],
      ])),
      new Response(body: json_encode([
        'packages' => [
          'drupal/helfi_api_base' => [
            [
              'name' => 'drupal/helfi_api_base',
              'version' => '1.0.18',
            ],
          ],
        ],
      ])),
    ]);

    $sut = PackageScannerDrushCommands::create($this->container, $this->getDrushContainer());
    // Test with old version and make sure we exit with failure.
    $return = $sut->checkVersions(__DIR__ . '/../../fixtures/composer.lock');
    $this->assertEquals(DrushCommands::EXIT_FAILURE_WITH_CLARITY, $return->getExitCode());
    $rows = $return->getOutputData();
    $this->assertInstanceOf(RowsOfFields::class, $rows);
    $this->assertEquals([
      [
        'name' => 'drupal/helfi_api_base',
        'version' => '1.0.18',
        'latest' => '1.1.0',
      ],
    ], $rows->getArrayCopy());

    // Test with up-to-date version and make sure we exit with success.
    $return = $sut->checkVersions(__DIR__ . '/../../fixtures/composer.lock');
    $this->assertEquals(DrushCommands::EXIT_SUCCESS, $return->getExitCode());
  }

}
