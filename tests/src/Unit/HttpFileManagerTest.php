<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_drupal_tools\Unit;

use DrupalTools\HttpFileManager;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Tests http file manager.
 *
 * @group helfi_drupal_tools
 */
class HttpFileManagerTest extends TestCase {

  /**
   * Creates HTTP client stub.
   *
   * @param \Psr\Http\Message\ResponseInterface[]|\GuzzleHttp\Exception\GuzzleException[] $responses
   *   The expected responses.
   *
   * @return \GuzzleHttp\Client
   *   The client.
   */
  protected function createMockHttpClient(array $responses) : Client {
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);

    return new Client(['handler' => $handlerStack]);
  }

  /**
   * Tests copy file.
   */
  public function testCopyFile() : void {
    $filename = '/tmp/' . uniqid();
    $client = $this->createMockHttpClient([
      new Response(body: '123'),
    ]);
    $manager = new HttpFileManager($client);
    $manager->copyFile('http://localhost/file', $filename);

    $content = file_get_contents($filename);
    $this->assertSame('123', $content);
  }

}
