<?php

declare(strict_types = 1);

namespace DrupalTools\Update;

/**
 * A DTO to store update results.
 */
final class UpdateResult {

  /**
   * Constructs a new instance.
   *
   * @param array $messages
   *   The messages.
   */
  public function __construct(
    public readonly array $messages = [],
  ) {
  }

}
