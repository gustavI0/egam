<?php

namespace Drupal\egam_dashboard\Dashboard;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\egam_global\Entities;

/**
 * Figures behind the dashboard blocks.
 *
 * Queries ignore access on purpose: the dashboard counts the whole catalogue,
 * drafts included. Blocks only show the names the current user may edit.
 */
class DashboardStats {

  /**
   * How many examples each data gap shows.
   */
  public const SAMPLE_SIZE = 5;

  /**
   * Missing values on artworks: check id => [field, description].
   */
  private const ARTWORK_GAPS = [
    'artwork_without_type' => ['field_artwork_type', 'Artworks without a type'],
    'artwork_without_artist' => ['field_artist', 'Artworks without an artist'],
    'artwork_without_museum' => ['field_museum', 'Artworks without a museum'],
    'artwork_without_cover' => ['field_cover', 'Artworks without a cover image'],
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $fieldManager,
  ) {}

  /**
   * @return \Drupal\egam_dashboard\Dashboard\EntityCount[]
   */
  public function counts(): array {
    $counts = [];
    foreach (Entities::cases() as $entity) {
      $published = $this->countWhere($entity, 1);
      $counts[] = new EntityCount($entity, $published, $this->countWhere($entity, 0));
    }
    return $counts;
  }

  /**
   * @return \Drupal\egam_dashboard\Dashboard\DataGap[]
   */
  public function gaps(): array {
    $gaps = [];
    foreach (self::ARTWORK_GAPS as $id => [$field, $label]) {
      if (!$this->hasField(Entities::Artwork, $field)) {
        continue;
      }
      $query = $this->query(Entities::Artwork)->notExists($field);
      $gaps[] = $this->gap($id, Entities::Artwork, $label, $query);
    }
    if ($this->hasField(Entities::Screenshot, 'field_game')) {
      $gaps[] = $this->gap(
        'game_without_artwork',
        Entities::Game,
        'Games without any artwork',
        $this->query(Entities::Game)->condition('id', $this->gamesWithScreenshots() ?: [0], 'NOT IN'),
      );
    }
    return $gaps;
  }

  /**
   * The latest changed entities of every type, newest first.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   */
  public function recent(int $limit): array {
    $candidates = [];
    foreach (Entities::cases() as $entity) {
      $storage = $this->entityTypeManager->getStorage($entity->value);
      $ids = $this->query($entity)->sort('changed', 'DESC')->range(0, $limit)->execute();
      array_push($candidates, ...array_values($storage->loadMultiple($ids)));
    }
    usort($candidates, static fn ($a, $b): int => $b->getChangedTime() <=> $a->getChangedTime());
    return array_slice($candidates, 0, $limit);
  }

  /**
   * Whether the field exists: editors may add or delete fields in Field UI.
   */
  private function hasField(Entities $entity, string $field): bool {
    return isset($this->fieldManager->getFieldStorageDefinitions($entity->value)[$field]);
  }

  private function query(Entities $entity) {
    return $this->entityTypeManager->getStorage($entity->value)->getQuery()->accessCheck(FALSE);
  }

  private function countWhere(Entities $entity, int $status): int {
    return (int) $this->query($entity)->condition('status', $status)->count()->execute();
  }

  private function gap(string $id, Entities $entity, string $label, $query): DataGap {
    $count = (int) (clone $query)->count()->execute();
    $samples = [];
    if ($count > 0) {
      $ids = $query->sort('id')->range(0, self::SAMPLE_SIZE)->execute();
      $samples = array_values($this->entityTypeManager->getStorage($entity->value)->loadMultiple($ids));
    }
    return new DataGap($id, $entity, $label, $count, $samples);
  }

  /**
   * Ids of the games that at least one screenshot refers to.
   */
  private function gamesWithScreenshots(): array {
    $rows = $this->entityTypeManager->getStorage('screenshot')->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('field_game.target_id')
      ->execute();
    return array_values(array_filter(array_column($rows, 'field_game_target_id')));
  }

}
