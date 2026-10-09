<?php

namespace Drupal\egam_dashboard\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\egam_dashboard\Dashboard\DashboardStats;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The latest changes across the five entity types.
 */
#[Block(
  id: 'egam_dashboard_recent',
  admin_label: new TranslatableMarkup('EGAM latest changes'),
  category: new TranslatableMarkup('EGAM'),
)]
final class RecentBlock extends DashboardBlockBase {

  private const LIMIT = 10;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    DashboardStats $stats,
    EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $stats, $entityTypeManager);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(DashboardStats::class),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  public function build(): array {
    $rows = [];
    foreach ($this->stats->recent(self::LIMIT) as $entity) {
      if (!$entity->access('update')) {
        continue;
      }
      $rows[] = [
        (string) $entity->getEntityType()->getSingularLabel(),
        ['data' => ['#type' => 'link', '#title' => $entity->label(), '#url' => $entity->toUrl('edit-form')]],
        $this->dateFormatter->format($entity->getChangedTime(), 'short'),
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('Type'), $this->t('Title'), $this->t('Changed')],
      '#rows' => $rows,
      '#empty' => $this->t('No changes yet.'),
      '#cache' => ['tags' => $this->listTags(), 'contexts' => ['user.permissions']],
    ];
  }

}
