<?php

namespace Drupal\egam_export\Export;

use Drupal\Core\File\FileSystemInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Writes export tables as one XLSX workbook, one sheet per entity type.
 */
class XlsxExporter {

  private const COLUMN_WIDTH = 28;
  private const ID_COLUMN_WIDTH = 8;

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * @param list<ExportTable> $tables
   *
   * @return string
   *   Path of a temporary XLSX file. The caller deletes it.
   *
   * @throws \Drupal\egam_export\Export\ExportException
   */
  public function export(array $tables): string {
    $byEntity = [];
    foreach ($tables as $table) {
      $byEntity[$table->entity->value] = $table;
    }

    $spreadsheet = new Spreadsheet();
    foreach (array_values($tables) as $index => $table) {
      $sheet = $index === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
      $this->writeSheet($sheet, $table, $byEntity);
    }
    $spreadsheet->setActiveSheetIndex(0);

    $path = tempnam($this->fileSystem->getTempDirectory(), 'egam-xlsx-');
    if ($path === FALSE) {
      throw new ExportException('Could not create a temporary file for the XLSX export.');
    }
    try {
      (new Xlsx($spreadsheet))->save($path);
    }
    catch (\Exception $e) {
      @unlink($path);
      throw new ExportException('Could not write the XLSX file: ' . $e->getMessage(), 0, $e);
    }
    finally {
      $spreadsheet->disconnectWorksheets();
    }

    return $path;
  }

  /**
   * @param array<string, ExportTable> $byEntity
   *   Tables keyed by Entities value, used to resolve links between sheets.
   */
  private function writeSheet(Worksheet $sheet, ExportTable $table, array $byEntity): void {
    $sheet->setTitle($table->sheetName());

    foreach ($table->headers as $index => $header) {
      $column = $index + 1;
      $sheet->setCellValueExplicit([$column, 1], $header, DataType::TYPE_STRING);
      $sheet->getColumnDimensionByColumn($column)->setWidth(
        $index === 0 ? self::ID_COLUMN_WIDTH : self::COLUMN_WIDTH,
      );
    }
    $sheet->getStyle([1, 1, count($table->headers), 1])->getFont()->setBold(TRUE);
    $sheet->freezePane('A2');

    foreach ($table->rows as $rowIndex => $row) {
      foreach ($row as $columnIndex => $cell) {
        $this->writeCell($sheet, $columnIndex + 1, $rowIndex + 2, $cell, $columnIndex === 0, $byEntity);
      }
    }
  }

  /**
   * @param array<string, ExportTable> $byEntity
   */
  private function writeCell(Worksheet $sheet, int $column, int $row, ExportCell $cell, bool $isId, array $byEntity): void {
    if ($isId && ctype_digit($cell->text)) {
      // A real number sorts correctly in Excel.
      $sheet->setCellValue([$column, $row], (int) $cell->text);
      return;
    }

    // Always a string: nothing the content says can run as a formula.
    $sheet->setCellValueExplicit([$column, $row], $cell->text, DataType::TYPE_STRING);

    $url = $this->hyperlinkFor($cell, $byEntity);
    if ($url === NULL) {
      return;
    }
    $sheet->getCell([$column, $row])->getHyperlink()->setUrl($url);
    $sheet->getStyle([$column, $row])->getFont()
      ->setUnderline(Font::UNDERLINE_SINGLE)
      ->getColor()->setARGB(Color::COLOR_BLUE);
  }

  /**
   * @param array<string, ExportTable> $byEntity
   */
  private function hyperlinkFor(ExportCell $cell, array $byEntity): ?string {
    if ($cell->externalUrl !== NULL) {
      return $cell->externalUrl;
    }
    if ($cell->linkEntity === NULL || $cell->linkId === NULL) {
      return NULL;
    }
    $target = $byEntity[$cell->linkEntity->value] ?? NULL;
    $targetRow = $target?->rowNumberFor($cell->linkId);
    if ($targetRow === NULL) {
      return NULL;
    }
    return sprintf("sheet://'%s'!A%d", $target->sheetName(), $targetRow);
  }

}
