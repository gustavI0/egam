<?php

namespace Drupal\egam_dashboard\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Entities that miss a value, with a few examples to fix right away.
 */
#[Block(
  id: 'egam_dashboard_gaps',
  admin_label: new TranslatableMarkup('EGAM data to complete'),
  category: new TranslatableMarkup('EGAM'),
)]
final class GapsBlock extends DashboardBlockBase {

  public function build(): array {
    $cache = ['tags' => $this->listTags(), 'contexts' => ['user.permissions']];
    $items = [];
    foreach ($this->stats->gaps() as $gap) {
      if ($gap->count === 0) {
        continue;
      }
      $samples = [];
      foreach ($gap->samples as $entity) {
        if ($entity->access('update')) {
          $samples[] = ['#type' => 'link', '#title' => $entity->label(), '#url' => $entity->toUrl('edit-form')];
        }
      }
      $items[] = [
        '#markup' => $this->t('@label: @count', ['@label' => $this->t($gap->label), '@count' => $gap->count]),
        'samples' => ['#theme' => 'item_list', '#items' => $samples],
      ];
    }
    if (!$items) {
      return ['#markup' => $this->t('Nothing is missing.'), '#cache' => $cache];
    }
    return ['#theme' => 'item_list', '#items' => $items, '#cache' => $cache];
  }

}
