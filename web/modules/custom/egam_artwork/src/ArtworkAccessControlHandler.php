<?php declare(strict_types = 1);

namespace Drupal\egam_artwork;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the artwork entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class ArtworkAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    return match($operation) {
      'view' => AccessResult::allowedIfHasPermissions($account, ['view artwork', 'administer artwork'], 'OR')
        ->andIf(AccessResult::allowedIf($entity->isPublished() || $account->hasPermission('administer artwork')))
        ->addCacheableDependency($entity),
      'update' => AccessResult::allowedIfHasPermissions($account, ['edit artwork', 'administer artwork'], 'OR'),
      'delete' => AccessResult::allowedIfHasPermissions($account, ['delete artwork', 'administer artwork'], 'OR'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create artwork', 'administer artwork'], 'OR');
  }

}
