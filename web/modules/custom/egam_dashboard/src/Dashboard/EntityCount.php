<?php

namespace Drupal\egam_dashboard\Dashboard;

use Drupal\egam_global\Entities;

/**
 * How many entities of one type exist, split by publication status.
 */
final readonly class EntityCount {

  public function __construct(
    public Entities $entity,
    public int $published,
    public int $unpublished,
  ) {}

  public function total(): int {
    return $this->published + $this->unpublished;
  }

}
