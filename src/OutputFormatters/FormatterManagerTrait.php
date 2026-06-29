<?php

declare(strict_types=1);

namespace DrupalTools\OutputFormatters;

use Consolidation\OutputFormatters\FormatterManager;

/**
 * A trait to manage custom formatters.
 */
trait FormatterManagerTrait {

  /**
   * The formatter manager.
   *
   * @var \Consolidation\OutputFormatters\FormatterManager
   */
  protected readonly FormatterManager $formatterManager;

  /**
   * Populates required formatter managers.
   */
  public function populateFormatterManager(): void {
    // @todo Refactor this to use Symfony Listeners once Drush supports psr-4 listeners.
    if (!$this->formatterManager->hasFormatter('markdown_table')) {
      $this->formatterManager->addFormatter('markdown_table', new MarkdownTableFormatter());
    }
  }

}
