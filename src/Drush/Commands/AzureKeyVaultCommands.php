<?php

declare(strict_types = 1);

namespace DrupalTools\Drush\Commands;

use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use Symfony\Component\Process\Process;

/**
 * A command to manage Azure Key vault.
 */
final class AzureKeyVaultCommands extends DrushCommands {

  private function invoceAz(array $command) {
    $process = new Process(['az', ...$command]);
    $process->setTty(TRUE);
    $process->run();
  }

  #[Command(name: 'helfi:tools:azure:update-secret')]
  public function update() : int {
    $this->invoceAz(['keyvault', 'list']);
    return DrushCommands::EXIT_SUCCESS;
  }

}
