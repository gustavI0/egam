<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_export\Export\ExportCell;
use Drupal\egam_export\Export\ExportTable;
use Drupal\egam_export\Export\XlsxExporter;
use Drupal\egam_global\Entities;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

#[RunTestsInSeparateProcesses]
class XlsxExporterTest extends ExportKernelTestBase {

  private array $paths = [];

  protected function tearDown(): void {
    foreach ($this->paths as $path) {
      @unlink($path);
    }
    parent::tearDown();
  }

  /**
   * @param array<string, list<list<ExportCell>>> $rowsByEntity
   *   Rows keyed by Entities value; missing entities get no rows.
   */
  private function export(array $rowsByEntity = []): Spreadsheet {
    $tables = array_map(
      static fn (Entities $entity): ExportTable => new ExportTable(
        $entity,
        ['id', 'label'],
        $rowsByEntity[$entity->value] ?? [],
      ),
      Entities::cases(),
    );
    $path = $this->container->get(XlsxExporter::class)->export($tables);
    $this->paths[] = $path;
    return IOFactory::load($path);
  }

  public function testOneSheetPerEntityInEnumOrder(): void {
    $spreadsheet = $this->export();
    $this->assertSame(
      ['Artworks', 'Artists', 'Games', 'Musea', 'Screenshots'],
      $spreadsheet->getSheetNames(),
    );
  }

  public function testHeaderRowIsBoldAndFrozen(): void {
    $sheet = $this->export()->getSheetByName('Artworks');
    $this->assertSame('id', $sheet->getCell('A1')->getValue());
    $this->assertSame('label', $sheet->getCell('B1')->getValue());
    $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
    $this->assertSame('A2', $sheet->getFreezePane());
  }

  public function testEmptyEntityHasOnlyTheHeaderRow(): void {
    $sheet = $this->export()->getSheetByName('Games');
    $this->assertSame(1, $sheet->getHighestRow());
  }

  public function testIdColumnIsNumeric(): void {
    $sheet = $this->export([
      'artist' => [[new ExportCell('12'), new ExportCell('Leonardo')]],
    ])->getSheetByName('Artists');
    $this->assertSame(12, $sheet->getCell('A2')->getValue());
  }

  public function testReferenceLinksToTheRowOfItsTarget(): void {
    $sheet = $this->export([
      'artist' => [
        [new ExportCell('11'), new ExportCell('Raphael')],
        [new ExportCell('12'), new ExportCell('Leonardo')],
      ],
      'artwork' => [
        [new ExportCell('1'), new ExportCell('Leonardo', 'Leonardo (#12)', Entities::Artist, '12')],
      ],
    ])->getSheetByName('Artworks');

    $cell = $sheet->getCell('B2');

    $this->assertSame('Leonardo', $cell->getValue());
    $this->assertTrue($cell->hasHyperlink());
    $this->assertStringContainsString("'Artists'!A3", $cell->getHyperlink()->getUrl());
  }

  public function testReferenceToAMissingRowHasNoLink(): void {
    $sheet = $this->export([
      'artwork' => [
        [new ExportCell('1'), new ExportCell('Ghost', 'Ghost (#99)', Entities::Artist, '99')],
      ],
    ])->getSheetByName('Artworks');

    $this->assertFalse($sheet->getCell('B2')->hasHyperlink());
  }

  public function testCellWithoutLinkInformationHasNoLink(): void {
    $sheet = $this->export([
      'artwork' => [[new ExportCell('1'), new ExportCell('#999')]],
    ])->getSheetByName('Artworks');

    $this->assertFalse($sheet->getCell('B2')->hasHyperlink());
  }

  public function testExternalUrlBecomesAHyperlink(): void {
    $sheet = $this->export([
      'artwork' => [[new ExportCell('1'), new ExportCell('https://example.com/artwork/1', externalUrl: 'https://example.com/artwork/1')]],
    ])->getSheetByName('Artworks');

    $this->assertSame('https://example.com/artwork/1', $sheet->getCell('B2')->getHyperlink()->getUrl());
  }

  public function testTextStartingWithEqualsIsNeverAFormula(): void {
    $sheet = $this->export([
      'artwork' => [[new ExportCell('1'), new ExportCell('=1+1')]],
    ])->getSheetByName('Artworks');

    $cell = $sheet->getCell('B2');

    $this->assertSame('s', $cell->getDataType());
    $this->assertSame('=1+1', $cell->getValue());
  }

  public function testSpecialCharactersSurviveARoundTrip(): void {
    $label = "Éloïse 🎨 — \"café\"\nline 2";
    $sheet = $this->export([
      'artwork' => [[new ExportCell('1'), new ExportCell($label)]],
    ])->getSheetByName('Artworks');

    $this->assertSame($label, $sheet->getCell('B2')->getValue());
  }

}
