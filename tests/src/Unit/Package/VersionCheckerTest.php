<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Unit\Package;

use DrupalTools\Package\ComposerOutdatedProcess;
use DrupalTools\Package\Exception\VersionCheckException;
use DrupalTools\Package\Version;
use DrupalTools\Package\VersionChecker;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Tests Version checker.
 *
 * @group helfi_api_base
 */
class VersionCheckerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests missing composer file.
   */
  public function testMissingComposerFile(): void {
    $process = $this->prophesize(ComposerOutdatedProcess::class);
    $process->run(Argument::any())->willReturn([]);
    $sut = new VersionChecker($process->reveal());

    $this->expectException(VersionCheckException::class);
    $sut->getOutdated('nonexistent.lock');
  }

  /**
   * Tests composer command failure.
   */
  public function testProcessFailure(): void {
    $process = $this->prophesize(ComposerOutdatedProcess::class);
    $process
      ->run(Argument::any())
      ->shouldBeCalled()
      ->willThrow($this->prophesize(ProcessFailedException::class)->reveal());

    $sut = new VersionChecker($process->reveal());

    $this->expectException(VersionCheckException::class);
    $sut->getOutdated(__DIR__ . '/../../../fixtures/composer.lock');
  }

  /**
   * Tests getOutdated().
   */
  public function testGetOutdated(): void {
    $process = $this->prophesize(ComposerOutdatedProcess::class);
    $process
      ->run(Argument::any())
      ->shouldBeCalled()
      ->willReturn([
        'installed' => [
          [
            'name' => 'drupal/helfi_api_base',
            'version' => '1.0.18',
            'latest' => '1.1.0',
          ],
        ],
      ]);

    $sut = new VersionChecker($process->reveal());

    $outdated = $sut->getOutdated(__DIR__ . '/../../../fixtures/composer.lock');

    $this->assertNotEmpty($outdated);
    $outdated = reset($outdated);
    $this->assertInstanceOf(Version::class, $outdated);
    $this->assertEquals('drupal/helfi_api_base', $outdated->name);
    $this->assertEquals('1.0.18', $outdated->version);
    $this->assertEquals('1.1.0', $outdated->latestVersion);
  }

}
