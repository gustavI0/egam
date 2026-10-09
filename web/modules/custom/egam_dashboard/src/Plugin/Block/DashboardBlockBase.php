<?php

namespace Drupal\egam_dashboard\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\egam_dashboard\Dashboard\DashboardStats;
use Drupal\egam_global\Entities;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Common wiring of the dashboard blocks.
 */
abstract class DashboardBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly DashboardStats $stats,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(DashboardStats::class),
      $container->get('entity_type.manager'),
    );
  }

  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'access administration pages');
  }

  /**
   * Cache tags that change when any entity of the given types changes.
   *
   * @param \Drupal\egam_global\Entities[] $entities
   */
  protected function listTags(array $entities = []): array {
    $tags = [];
    foreach ($entities ?: Entities::cases() as $entity) {
      $tags = array_merge($tags, $this->entityTypeManager->getDefinition($entity->value)->getListCacheTags());
    }
    return $tags;
  }

  protected function collectionLabel(Entities $entity): string {
    return (string) $this->entityTypeManager->getDefinition($entity->value)->getCollectionLabel();
  }

  public function getCacheContexts(): array {
    return ['user.permissions'];
  }

}
