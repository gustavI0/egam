<?php

namespace Drupal\Tests\egam_dashboard\Kernel;

use Drupal\egam_global\Entities;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Shared setup: the EGAM entities and the fields the dashboard reads.
 */
abstract class DashboardKernelTestBase extends KernelTestBase {

  protected static $modules = [
    'system', 'user', 'field', 'text', 'options', 'taxonomy', 'filter',
    'egam_global', 'egam_artwork', 'egam_artist', 'egam_game',
    'egam_museum', 'egam_screenshot', 'egam_export', 'egam_dashboard',
    'dashboard', 'layout_builder', 'layout_discovery', 'contextual', 'views',
  ];

  protected function setUp(): void {
    parent::setUp();
    // Date formats, needed to show the time of the latest changes.
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    foreach (Entities::cases() as $case) {
      $this->installEntitySchema($case->value);
    }

    $this->container->get("router.builder")->rebuild();
    $this->addReference('artwork', 'field_artwork_type', 'taxonomy_term');
    $this->addReference('artwork', 'field_artist', 'artist');
    $this->addReference('artwork', 'field_museum', 'museum');
    // The real field points to media; any target works for emptiness checks.
    $this->addReference('artwork', 'field_cover', 'taxonomy_term');
    $this->addReference('screenshot', 'field_game', 'game');
    $this->addReference('screenshot', 'field_artwork', 'artwork');
  }

  protected function addReference(string $entityTypeId, string $name, string $targetType): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entityTypeId,
      'type' => 'entity_reference',
      'settings' => ['target_type' => $targetType],
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entityTypeId,
      'bundle' => $entityTypeId,
      'label' => $name,
    ])->save();
  }

  /**
   * Creates an entity of the given type.
   */
  protected function make(Entities $entity, array $values = []): object {
    $storage = $this->container->get('entity_type.manager')->getStorage($entity->value);
    $created = $storage->create($values + ['label' => ucfirst($entity->value) . ' ' . random_int(1000, 9999)]);
    $created->save();
    return $created;
  }

  /**
   * A user who can see everything the dashboard shows.
   */
  protected function adminUser(): User {
    $user = User::create(['name' => 'admin', 'status' => 1]);
    $user->save();
    return $user;
  }

}
