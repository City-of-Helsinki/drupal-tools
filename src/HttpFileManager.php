<?php

declare(strict_types=1);

namespace DrupalTools;

use DrupalTools\Update\UpdateOptions;
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
  public function __construct(private readonly ClientInterface $client) {
  }

  /**
   * Downloads the given source file.
   *
   * @param string $source
   *   The source.
   * @param string $destination
   *   The file destination.
   * @param \DrupalTools\Update\UpdateOptions $options
   *   The update options.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function copyFile(string $source, string $destination, UpdateOptions $options) : void {
    $resource = Utils::tryFopen($destination, 'w');
    $this->client->request('GET', $options->branch . '/' . $source, ['sink' => $resource]);
  }

}
