<?php

declare(strict_types=1);

namespace DrupalTools\Update;

/**
 * Thrown when update hooks after the schema version have been removed.
 */
final class MissingUpdateHookException extends \RuntimeException {
}
