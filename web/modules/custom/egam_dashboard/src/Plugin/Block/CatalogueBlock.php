<?php

namespace Drupal\egam_dashboard\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Totals per entity type, split by publication status.
 */
#[Block(
  id: 'egam_dashboard_catalogue',
  admin_label: new TranslatableMarkup('EGAM catalogue'),
  category: new TranslatableMarkup('EGAM'),
)]
final class CatalogueBlock extends DashboardBlockBase {

  public function build(): array {
    $rows = [];
    foreach ($this->stats->counts() as $count) {
      $url = Url::fromRoute('entity.' . $count->entity->value . '.collection');
      $label = $this->collectionLabel($count->entity);
      $rows[] = [
        $url->access() ? ['data' => ['#type' => 'link', '#title' => $label, '#url' => $url]] : $label,
        $count->published,
        $count->unpublished,
        $count->total(),
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('Content'), $this->t('Published'), $this->t('Unpublished'), $this->t('Total')],
      '#rows' => $rows,
      '#cache' => ['tags' => $this->listTags(), 'contexts' => ['user.permissions']],
    ];
  }

}
