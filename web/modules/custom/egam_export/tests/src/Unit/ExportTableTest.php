<?php

namespace Drupal\Tests\egam_export\Unit;

use Drupal\egam_export\Export\ExportCell;
use Drupal\egam_export\Export\ExportTable;
use Drupal\egam_global\Entities;
use Drupal\Tests\UnitTestCase;

class ExportTableTest extends UnitTestCase {

  private function table(Entities $entity, array $ids): ExportTable {
    $rows = array_map(
      static fn (string $id): array => [new ExportCell($id), new ExportCell("Label $id")],
      $ids,
    );
    return new ExportTable($entity, ['id', 'label'], $rows);
  }

  public function testRowNumbersStartAfterTheHeaderRow(): void {
    $table = $this->table(Entities::Artist, ['10', '12']);
    $this->assertSame(2, $table->rowNumberFor('10'));
    $this->assertSame(3, $table->rowNumberFor(12));
  }

  public function testUnknownIdHasNoRowNumber(): void {
    $table = $this->table(Entities::Artist, ['10']);
    $this->assertNull($table->rowNumberFor('999'));
  }

  public function testEmptyTableHasNoRowNumbers(): void {
    $table = $this->table(Entities::Artist, []);
    $this->assertNull($table->rowNumberFor('1'));
  }

  public function testNamesComeFromThePluralForm(): void {
    $table = $this->table(Entities::Museum, []);
    $this->assertSame('Musea', $table->sheetName());
    $this->assertSame('musea.csv', $table->fileName());
  }

  public function testCsvTextFallsBackToText(): void {
    $this->assertSame('Leonardo', (new ExportCell('Leonardo'))->csv());
    $this->assertSame('Leonardo (#1)', (new ExportCell('Leonardo', 'Leonardo (#1)'))->csv());
  }

}
