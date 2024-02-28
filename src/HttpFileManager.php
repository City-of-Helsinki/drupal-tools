<?php

declare(strict_types=1);

namespace DrupalTools;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Utils;

/**
 * The HTTP file manager.
 */
class HttpFileManager {

  /**
   * Constructs a new object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   */
  public function __construct(private ClientInterface $client) {
  }

  /**
   * Downloads the given source file.
   *
   * @param string $source
   *   The source.
   * @param string $destination
   *   The file destination.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function copyFile(string $source, string $destination) : void {
    $resource = Utils::tryFopen($destination, 'w');
    $this->client->request('GET', $source, ['sink' => $resource]);
  }

}
