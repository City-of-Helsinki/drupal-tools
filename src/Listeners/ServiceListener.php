<?php

declare(strict_types=1);

namespace DrupalTools\Listeners;

use Drush\Event\ConsoleDefinitionsEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class ServiceListener {

  public function __invoke(ConsoleDefinitionsEvent $event): void {
    $x = 1;
  }

}
