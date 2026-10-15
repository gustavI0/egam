<?php

namespace Drupal\egam_dashboard\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Url;
use Drupal\egam_global\Entities;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Quick links: export first, then adding and listing content.
 */
#[Block(
  id: 'egam_dashboard_shortcuts',
  admin_label: new TranslatableMarkup('EGAM shortcuts'),
  category: new TranslatableMarkup('EGAM'),
)]
final class ShortcutsBlock extends DashboardBlockBase {

  public function build(): array {
    $build = ['#cache' => ['contexts' => ['user.permissions']]];

    $export = Url::fromRoute('egam_export.page');
    if ($export->access()) {
      $build['export'] = [
        '#type' => 'link',
        '#title' => $this->t('Export data (CSV / XLSX)'),
        '#url' => $export,
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }

    $add = [];
    $lists = [];
    foreach (Entities::cases() as $entity) {
      $label = $this->entityTypeManager->getDefinition($entity->value)->getSingularLabel();
      $addUrl = Url::fromRoute('entity.' . $entity->value . '.add_form');
      if ($addUrl->access()) {
        $add[] = ['#type' => 'link', '#title' => $this->t('Add @type', ['@type' => $label]), '#url' => $addUrl];
      }
      $listUrl = Url::fromRoute('entity.' . $entity->value . '.collection');
      if ($listUrl->access()) {
        $lists[] = ['#type' => 'link', '#title' => $this->collectionLabel($entity), '#url' => $listUrl];
      }
    }
    if ($add) {
      $build['add'] = $this->pills($this->t('Add content'), $add);
    }
    if ($lists) {
      $build['lists'] = $this->pills($this->t('Manage content'), $lists);
    }
    return $build;
  }

  /**
   * A titled group of links, styled as pills by the admin stylesheet.
   */
  private function pills(TranslatableMarkup $title, array $links): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['egam-pills']],
      'list' => ['#theme' => 'item_list', '#items' => $links, '#title' => $title],
    ];
  }

}
