<?php

/**
 * @file
 * Contains mock update hooks where the oldest hooks have been removed.
 */

declare(strict_types=1);

namespace DrupalToolsGapTest;

use DrupalTools\Update\UpdateResult;

/**
 * Tests that hooks after removed hooks are processed.
 */
function drupal_tools_update_5() : UpdateResult {
  return new UpdateResult(['message 5']);
}

/**
 * Tests that hooks after removed hooks are processed.
 */
function drupal_tools_update_6() : UpdateResult {
  return new UpdateResult(['message 6']);
}
