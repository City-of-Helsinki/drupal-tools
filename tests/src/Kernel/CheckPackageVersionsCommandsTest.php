<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Kernel;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\KernelTests\KernelTestBase;
use DrupalTools\Drush\Commands\PackageScannerDrushCommands;
use DrupalTools\OutputFormatters\MarkdownTableFormatter;
use DrupalTools\Package\ComposerOutdatedProcess;
use DrupalTools\Package\VersionChecker;
use Drush\Formatters\DrushFormatterManager;
use League\Container\Container;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Tests Package version checker commands.
 *
 * @group helfi_drupal_tools
 */
#[RunTestsInSeparateProcesses]
final class CheckPackageVersionsCommandsTest extends KernelTestBase {

  use ProphecyTrait;

  /**
   * Initialized a dummy Drush container.
   *
   * @return \Psr\Container\ContainerInterface
   *   The Drush container.
   */
  private function getDrushContainer() : ContainerInterface {
    $container = new Container();
    $container->add(FormatterManager::class, new DrushFormatterManager());
    return $container;
  }

  /**
   * Tests that Markdown table formatter is registered.
   */
  public function testMarkdownTableFormatter() : void {
    $container = $this->getDrushContainer();
    PackageScannerDrushCommands::create($container);
    $this->assertInstanceOf(MarkdownTableFormatter::class, $container->get(FormatterManager::class)->getFormatter('markdown_table'));
  }

  /**
   * Tests version check.
   */
  public function testVersionCheck() : void {
    $process = $this->prophesize(ComposerOutdatedProcess::class);
    $process->run(Argument::any())
      ->willReturn([
        // No packages need updating.
      ], [
        'installed' => [
          [
            'name' => 'drupal/helfi_api_base',
            'latest' => '1.1.0',
            'version' => '1.0.18',
          ],
        ],
      ]);
    $versionChecker = new VersionChecker($process->reveal());
    $sut = new PackageScannerDrushCommands(new FormatterManager(), $versionChecker);

    // Test with up-to-date version and make sure we exit with success.
    $return = $sut->doExecute(__DIR__ . '/../../fixtures/composer.lock');
    $this->assertEquals(Command::SUCCESS, $return->getExitCode());

    // Test with old version and make sure we exit with failure.
    $return = $sut->doExecute(__DIR__ . '/../../fixtures/composer.lock');
    $this->assertEquals(Command::FAILURE, $return->getExitCode());
    $rows = $return->getOutputData();
    $this->assertInstanceOf(RowsOfFields::class, $rows);
    $this->assertEquals([
      [
        'name' => 'drupal/helfi_api_base',
        'version' => '1.0.18',
        'latest' => '1.1.0',
      ],
    ], $rows->getArrayCopy());
  }

}
