<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_export\Export\CsvExporter;
use Drupal\egam_export\Export\ExportCell;
use Drupal\egam_export\Export\ExportTable;
use Drupal\egam_global\Entities;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
class CsvExporterTest extends ExportKernelTestBase {

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
  private function export(array $rowsByEntity = []): \ZipArchive {
    $tables = array_map(
      static fn (Entities $entity): ExportTable => new ExportTable(
        $entity,
        ['id', 'label'],
        $rowsByEntity[$entity->value] ?? [],
      ),
      Entities::cases(),
    );
    $path = $this->container->get(CsvExporter::class)->export($tables);
    $this->paths[] = $path;
    $zip = new \ZipArchive();
    $this->assertTrue($zip->open($path));
    return $zip;
  }

  /**
   * @return list<list<string>>
   */
  private function parseCsv(string $csv): array {
    $handle = fopen('php://temp', 'w+');
    fwrite($handle, $csv);
    rewind($handle);
    $records = [];
    while (($record = fgetcsv($handle, NULL, ',', '"', '')) !== FALSE) {
      $records[] = $record;
    }
    fclose($handle);
    return $records;
  }

  public function testZipContainsExactlyOneCsvPerEntity(): void {
    $zip = $this->export();
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $names[] = $zip->getNameIndex($i);
    }
    sort($names);
    $this->assertSame(['artists.csv', 'artworks.csv', 'games.csv', 'musea.csv', 'screenshots.csv'], $names);
  }

  public function testFilesStartWithAUtf8Bom(): void {
    $zip = $this->export();
    $this->assertStringStartsWith("\xEF\xBB\xBF", $zip->getFromName('artworks.csv'));
  }

  public function testEmptyTableHasOnlyTheHeaderRow(): void {
    $zip = $this->export();
    $records = $this->parseCsv(substr($zip->getFromName('artists.csv'), 3));
    $this->assertSame([['id', 'label']], $records);
  }

  public function testSpecialCharactersSurviveARoundTrip(): void {
    $label = "Hello, \"World\"\nsecond line";
    $zip = $this->export([
      'artwork' => [
        [new ExportCell('1'), new ExportCell($label)],
        [new ExportCell('2'), new ExportCell('Éloïse 🎨 — café')],
      ],
    ]);

    $records = $this->parseCsv(substr($zip->getFromName('artworks.csv'), 3));

    $this->assertSame([
      ['id', 'label'],
      ['1', $label],
      ['2', 'Éloïse 🎨 — café'],
    ], $records);
  }

  public function testReferencesUseTheCsvText(): void {
    $zip = $this->export([
      'artwork' => [[new ExportCell('1'), new ExportCell('Leonardo', 'Leonardo (#7)')]],
    ]);

    $records = $this->parseCsv(substr($zip->getFromName('artworks.csv'), 3));

    $this->assertSame('Leonardo (#7)', $records[1][1]);
  }

}
