<?php

declare(strict_types=1);

namespace DrupalTools\OutputFormatters;

use Consolidation\OutputFormatters\FormatterManager;
use Psr\Container\ContainerInterface;

/**
 * A trait to manage custom formatters.
 */
trait FormatterManagerTrait {

  /**
   * Populates required formatter managers.
   *
   * @param \Psr\Container\ContainerInterface $drush
   *   The drush container.
   */
  public static function populateFormatterManager(ContainerInterface $drush): void {
    /** @var \Consolidation\OutputFormatters\FormatterManager $formatterManager */
    $formatterManager = $drush->get('formatterManager');

    if (!$formatterManager instanceof FormatterManager) {
      throw new \LogicException('Formatter manager is not set.');
    }

    // @todo Figure out if there's a better way to inject this service.
    if (!$formatterManager->hasFormatter('markdown_table')) {
      $formatterManager->addFormatter('markdown_table', new MarkdownTableFormatter());
    }
  }

}
