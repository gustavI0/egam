<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_global\Entities;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Shared setup: EGAM entities plus a representative set of fields.
 */
abstract class ExportKernelTestBase extends KernelTestBase {

  protected static $modules = [
    'system', 'user', 'field', 'text', 'options', 'link', 'file', 'image',
    'datetime', 'taxonomy', 'filter',
    'egam_global', 'egam_artwork', 'egam_artist', 'egam_game',
    'egam_museum', 'egam_screenshot', 'egam_export',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    foreach (Entities::cases() as $case) {
      $this->installEntitySchema($case->value);
    }
    $this->installSchema('file', ['file_usage']);

    $this->addField('artwork', 'field_date', 'string');
    $this->addField('artwork', 'field_sorting_year', 'integer');
    $this->addField('artwork', 'field_more_info', 'link');
    $this->addField('artwork', 'field_artist_prefix', 'list_string', [
      'allowed_values' => ['from' => "D'après", 'attributed_to' => 'Attribué à'],
    ]);
    $this->addField('artwork', 'field_artist', 'entity_reference', ['target_type' => 'artist']);
    $this->addField('artwork', 'field_museum', 'entity_reference', ['target_type' => 'museum']);
    $this->addField('artwork', 'field_subject', 'entity_reference', ['target_type' => 'taxonomy_term'], -1);
    $this->addField('artwork', 'field_photo', 'image');
    $this->addField('artwork', 'field_attachment', 'entity_reference', ['target_type' => 'file']);
    $this->addField('screenshot', 'field_artwork', 'entity_reference', ['target_type' => 'artwork'], -1);
    $this->addField('screenshot', 'field_game', 'entity_reference', ['target_type' => 'game']);

    $this->container->get('router.builder')->rebuild();
  }

  protected function addField(string $entityTypeId, string $name, string $type, array $storageSettings = [], int $cardinality = 1): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entityTypeId,
      'type' => $type,
      'settings' => $storageSettings,
      'cardinality' => $cardinality,
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entityTypeId,
      'bundle' => $entityTypeId,
      'label' => $name,
    ])->save();
  }

}
