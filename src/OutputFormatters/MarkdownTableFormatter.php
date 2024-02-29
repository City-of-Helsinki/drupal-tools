<?php

declare(strict_types=1);

namespace DrupalTools\OutputFormatters;

use Consolidation\OutputFormatters\Exception\IncompatibleDataException;
use Consolidation\OutputFormatters\Formatters\FormatterInterface;
use Consolidation\OutputFormatters\Formatters\HumanReadableFormat;
use Consolidation\OutputFormatters\Formatters\MetadataFormatterInterface;
use Consolidation\OutputFormatters\Formatters\MetadataFormatterTrait;
use Consolidation\OutputFormatters\Formatters\RenderDataInterface;
use Consolidation\OutputFormatters\Formatters\RenderTableDataTrait;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\TableDataInterface;
use Consolidation\OutputFormatters\Transformations\TableTransformation;
use Consolidation\OutputFormatters\Validate\ValidDataTypesInterface;
use Consolidation\OutputFormatters\Validate\ValidDataTypesTrait;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Formats a table using markdown formatter.
 */
final class MarkdownTableFormatter implements FormatterInterface, ValidDataTypesInterface, RenderDataInterface, MetadataFormatterInterface, HumanReadableFormat {

  use ValidDataTypesTrait;
  use RenderTableDataTrait;
  use MetadataFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public function write(
    OutputInterface $output,
    $data,
    FormatterOptions $options
  ) : void {
    assert($data instanceof TableTransformation);
    $headers = $data->getHeaders();
    $rows = $data->getTableData();

    $output->write(self::renderLine($headers));
    $output->write(self::renderLine(array_map(fn() => '--', $headers)));
    $output->write(implode('', array_map(fn(array $row) => self::renderLine($row), $rows)));
  }

  /**
   * Renders a line.
   *
   * @param array $fields
   *   Renders the given fields.
   *
   * @return string
   *   The rendered line.
   */
  private static function renderLine(array $fields) : string {
    return implode(' | ', $fields) . "\n";
  }

  /**
   * {@inheritdoc}
   *
   * @return array{\ReflectionClass<\Consolidation\OutputFormatters\StructuredData\RowsOfFields|\Consolidation\OutputFormatters\StructuredData\PropertyList>}
   *   The valid data types.
   */
  public function validDataTypes() : array {
    return [
      new \ReflectionClass('\Consolidation\OutputFormatters\StructuredData\RowsOfFields'),
      new \ReflectionClass('\Consolidation\OutputFormatters\StructuredData\PropertyList'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validate($structuredData) : TableDataInterface {
    // If the provided data was of class RowsOfFields
    // or PropertyList, it will be converted into
    // a TableTransformation object by the restructure call.
    if (!$structuredData instanceof TableDataInterface) {
      throw new IncompatibleDataException(
        $this,
        $structuredData,
        $this->validDataTypes()
      );
    }
    return $structuredData;
  }

}
