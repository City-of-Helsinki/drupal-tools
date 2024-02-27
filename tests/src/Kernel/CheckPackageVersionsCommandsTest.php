<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use DrupalTools\Drush\Commands\PackageScannerDrushCommands;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Style\OutputStyle;

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
   * Tests version check with invalid composer.json file.
   */
  public function testInvalidComposerFile() : void {
    $this->expectException(\RuntimeException::class);
    PackageScannerDrushCommands::create($this->container, Drush::getContainer())->checkVersions('nonexistent.lock');
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
    $io = $this->prophesize(OutputStyle::class);
    $io->table(Argument::any(), [
      [
        'name' => 'drupal/helfi_api_base',
        'currentVersion' => '1.0.18',
        'latestVersion' => '1.1.0',
      ],
    ])->shouldBeCalledTimes(1);

    $sut = new PackageScannerDrushCommands(
      $this->container->get('helfi_api_base.package_version_checker'),
      $io->reveal(),
    );
    // Test with old version and make sure we exit with failure.
    $return = $sut->checkVersions(__DIR__ . '/../../fixtures/composer.lock');
    $this->assertEquals(DrushCommands::EXIT_FAILURE_WITH_CLARITY, $return);

    // Test with up-to-date version and make sure we exit with success.
    $return = $sut->checkVersions(__DIR__ . '/../../fixtures/composer.lock');
    $this->assertEquals(DrushCommands::EXIT_SUCCESS, $return);
  }

}
