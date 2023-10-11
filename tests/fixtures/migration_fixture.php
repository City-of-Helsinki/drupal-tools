<?php

declare(strict_types = 1);

namespace DrupalToolsTest;

use DrupalTools\Update\UpdateResult;

function drupal_tools_update_1() : UpdateResult {
  return new UpdateResult(['message 0']);
}

function drupal_tools_update_2() : UpdateResult {
  return new UpdateResult(['message 1']);
}
