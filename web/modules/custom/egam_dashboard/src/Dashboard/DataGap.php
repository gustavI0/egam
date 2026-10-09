<?php

namespace Drupal\egam_dashboard\Dashboard;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\egam_global\Entities;

/**
 * Entities that miss a value the editors want filled in.
 */
final readonly class DataGap {

  /**
   * @param string $id
   *   Stable machine name of the check.
   * @param \Drupal\egam_global\Entities $entity
   *   The entity type that is checked.
   * @param string $label
   *   Human readable description of what is missing (untranslated).
   * @param int $count
   *   How many entities miss the value.
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $samples
   *   A few of them, to fix straight from the dashboard.
   */
  public function __construct(
    public string $id,
    public Entities $entity,
    public string $label,
    public int $count,
    public array $samples,
  ) {}

}
