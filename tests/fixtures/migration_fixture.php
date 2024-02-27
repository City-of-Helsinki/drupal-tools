<?php

/**
 * @file
 * Contains mock update hooks for tests.
 */

declare(strict_types=1);

namespace DrupalToolsTest;

use DrupalTools\Update\FileManager;
use DrupalTools\Update\UpdateOptions;
use DrupalTools\Update\UpdateResult;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests that hooks are processed.
 */
function drupal_tools_update_1(UpdateOptions $options, FileManager $fileManager, Filesystem $filesystem) : UpdateResult {
  return new UpdateResult(['message 0']);
}

/**
 * Tests that hooks are processed.
 */
function drupal_tools_update_2() : UpdateResult {
  return new UpdateResult(['message 1']);
}
