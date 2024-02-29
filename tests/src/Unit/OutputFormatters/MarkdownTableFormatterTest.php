<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_drupal_tools\Unit;

use Consolidation\OutputFormatters\Exception\IncompatibleDataException;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RestructureInterface;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\TableDataInterface;
use Consolidation\OutputFormatters\StructuredData\UnstructuredData;
use DrupalTools\OutputFormatters\MarkdownTableFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests Markdown table formatter.
 *
 * @group helfi_drupal_tools
 */
class MarkdownTableFormatterTest extends TestCase {

  /**
   * Make sure an exception is thrown for incompatible data.
   */
  public function testInvalidDataTypeException() : void {
    $formatter = new MarkdownTableFormatter();
    $data = new UnstructuredData([]);
    $this->expectException(IncompatibleDataException::class);
    $formatter->validate($data->restructure(new FormatterOptions()));
  }

  /**
   * Tests validation.
   *
   * @dataProvider validateTestData
   */
  public function testValidate(RestructureInterface $data) : void {
    $formatter = new MarkdownTableFormatter();
    $data = $formatter->validate($data->restructure(new FormatterOptions()));
    $this->assertInstanceOf(TableDataInterface::class, $data);
  }

  /**
   * A data provider for validation tests.
   *
   * @return array
   *   The data.
   */
  public function validateTestData() : array {
    return [
      [new RowsOfFields([])],
      [new PropertyList([])],
    ];
  }

  /**
   * Make sure the given table is formatted as markdown.
   */
  public function testMarkdownTableFormatter() : void {
    $formatter = new MarkdownTableFormatter();
    $output = new BufferedOutput();
    $options = new FormatterOptions([
      FormatterOptions::FIELD_LABELS => [
        'name' => 'Name',
        'version' => 'Version',
        'latest' => 'Latest',
      ],
    ]);

    $data = new RowsOfFields([]);
    $data = $data->restructure($options);
    $formatter->write($output, $data, $options);

    $expected = "Name | Version | Latest\n";
    $expected .= "-- | -- | --\n";
    $this->assertEquals($expected, $output->fetch());

    $data = new RowsOfFields([
      [
        'name' => 'Test',
        'version' => '1.0.0',
        'latest' => '1.0.2',
      ],
      [
        'name' => 'Test 2',
        'version' => '',
        'latest' => '',
      ],
    ]);
    $data = $data->restructure($options);
    $formatter->write($output, $data, $options);

    $expected = "Name | Version | Latest\n";
    $expected .= "-- | -- | --\n";
    $expected .= "Test | 1.0.0 | 1.0.2\n";
    $expected .= "Test 2 |  | \n";

    $this->assertEquals($expected, $output->fetch());
  }

}
