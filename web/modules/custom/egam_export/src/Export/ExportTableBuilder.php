<?php

namespace Drupal\egam_export\Export;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\egam_global\Entities;

/**
 * Reads the EGAM entities and builds one ExportTable per entity type.
 */
class ExportTableBuilder {

  private const CHUNK_SIZE = 200;

  /**
   * Columns placed first and last, in this order.
   */
  private const LEADING_FIELDS = ['id', 'label', 'status'];
  private const TRAILING_FIELDS = ['created', 'changed', 'uid'];

  private const TECHNICAL_FIELDS = [
    'uuid',
    'langcode',
    'default_langcode',
    'revision_default',
    'revision_translation_affected',
  ];

  private const EXCLUDED_FIELD_TYPES = ['image', 'file', 'metatag'];

  private const EXCLUDED_TARGET_TYPES = ['media', 'file'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly FieldValueNormalizer $normalizer,
  ) {}

  /**
   * @return list<ExportTable>
   *   One table per entity type, in the order of Entities::cases().
   */
  public function buildAll(): array {
    return array_map($this->build(...), Entities::cases());
  }

  public function build(Entities $entity): ExportTable {
    $columns = $this->columns($entity->value);
    $headers = array_map(
      static fn (string $name): string => $name === 'uid' ? 'owner' : $name,
      $columns,
    );
    $headers[] = 'url';

    $storage = $this->entityTypeManager->getStorage($entity->value);
    $ids = array_values($storage->getQuery()->accessCheck(FALSE)->sort('id')->execute());

    $rows = [];
    foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
      $loaded = $storage->loadMultiple($chunk);
      ksort($loaded);
      foreach ($loaded as $content) {
        $row = [];
        foreach ($columns as $name) {
          $row[] = $this->normalizer->normalize($content->get($name));
        }
        $url = $content->toUrl('canonical', ['absolute' => TRUE])->toString();
        $row[] = new ExportCell($url, externalUrl: $url);
        $rows[] = $row;
      }
      $storage->resetCache($chunk);
    }

    return new ExportTable($entity, $headers, $rows);
  }

  /**
   * Field names to export, in column order (the url column is added apart).
   *
   * @return list<string>
   */
  private function columns(string $entityTypeId): array {
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    $technical = array_filter([
      ...self::TECHNICAL_FIELDS,
      $entityType->getKey('revision'),
      ...array_values($entityType->getRevisionMetadataKeys()),
    ]);
    $fixed = [...self::LEADING_FIELDS, ...self::TRAILING_FIELDS];

    $middle = [];
    $definitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $entityTypeId);
    foreach ($definitions as $name => $definition) {
      if (in_array($name, $fixed, TRUE) || in_array($name, $technical, TRUE)) {
        continue;
      }
      if ($this->isExcluded($definition)) {
        continue;
      }
      $middle[] = $name;
    }

    return [...self::LEADING_FIELDS, ...$middle, ...self::TRAILING_FIELDS];
  }

  private function isExcluded(FieldDefinitionInterface $definition): bool {
    if ($definition->isComputed()) {
      return TRUE;
    }
    if (in_array($definition->getType(), self::EXCLUDED_FIELD_TYPES, TRUE)) {
      return TRUE;
    }
    return $definition->getType() === 'entity_reference'
      && in_array($definition->getSetting('target_type'), self::EXCLUDED_TARGET_TYPES, TRUE);
  }

}
