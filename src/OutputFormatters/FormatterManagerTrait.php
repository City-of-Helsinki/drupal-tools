<?php

declare(strict_types=1);

namespace DrupalTools\OutputFormatters;

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
    /** @var \Drush\Formatters\DrushFormatterManager $formatterManager */
    $formatterManager = $drush->get('formatterManager');

    // @todo Figure out if there's a better way to inject this service.
    if (!$formatterManager->hasFormatter('markdown_table')) {
      $formatterManager->addFormatter('markdown_table', new MarkdownTableFormatter());
    }
  }

}

