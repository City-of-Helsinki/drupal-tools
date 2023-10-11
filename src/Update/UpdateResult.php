<?php

declare(strict_types = 1);

namespace DrupalTools\Update;

final class UpdateResult {

  public function __construct(
    public readonly array $messages = [],
  ) {
  }

}
