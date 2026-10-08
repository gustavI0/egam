<?php

namespace Drupal\egam_export\Export;

use Drupal\Core\File\FileSystemInterface;

/**
 * Writes export tables as CSV files grouped in a zip archive.
 */
class CsvExporter {

  /**
   * Lets Excel detect UTF-8 so accents display correctly.
   */
  private const BOM = "\xEF\xBB\xBF";

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * @param list<ExportTable> $tables
   *
   * @return string
   *   Path of a temporary zip file. The caller deletes it.
   *
   * @throws \Drupal\egam_export\Export\ExportException
   */
  public function export(array $tables): string {
    if (!class_exists(\ZipArchive::class)) {
      throw new ExportException('The PHP zip extension is required for the CSV export.');
    }
    $path = tempnam($this->fileSystem->getTempDirectory(), 'egam-csv-');
    if ($path === FALSE) {
      throw new ExportException('Could not create a temporary file for the CSV export.');
    }

    $zip = new \ZipArchive();
    if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
      @unlink($path);
      throw new ExportException('Could not open the CSV archive for writing.');
    }
    foreach ($tables as $table) {
      $zip->addFromString($table->fileName(), self::BOM . $this->toCsv($table));
    }
    if (!$zip->close()) {
      @unlink($path);
      throw new ExportException('Could not write the CSV archive.');
    }

    return $path;
  }

  private function toCsv(ExportTable $table): string {
    $handle = fopen('php://temp', 'w+');
    fputcsv($handle, $table->headers, ',', '"', '', "\n");
    foreach ($table->rows as $row) {
      $values = array_map(static fn (ExportCell $cell): string => $cell->csv(), $row);
      fputcsv($handle, $values, ',', '"', '', "\n");
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);
    return $csv;
  }

}
