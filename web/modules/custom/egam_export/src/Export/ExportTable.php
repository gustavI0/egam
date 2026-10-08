<?php

namespace Drupal\egam_export\Export;

use Drupal\egam_global\Entities;

/**
 * The exported content of one entity type: headers and rows of cells.
 *
 * The first cell of every row is the entity id.
 */
final class ExportTable {

  /**
   * Sheet row number by entity id.
   *
   * @var array<string, int>
   */
  private array $rowNumbers = [];

  /**
   * @param list<string> $headers
   * @param list<list<ExportCell>> $rows
   */
  public function __construct(
    public readonly Entities $entity,
    public readonly array $headers,
    public readonly array $rows,
  ) {
    foreach ($rows as $index => $row) {
      // Row 1 of a sheet holds the headers.
      $this->rowNumbers[$row[0]->text] = $index + 2;
    }
  }

  public function sheetName(): string {
    return ucfirst((string) $this->entity->getPlural());
  }

  public function fileName(): string {
    return $this->entity->getPlural() . '.csv';
  }

  public function rowNumberFor(string|int $id): ?int {
    return $this->rowNumbers[(string) $id] ?? NULL;
  }

}
