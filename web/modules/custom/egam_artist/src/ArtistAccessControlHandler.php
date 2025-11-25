<?php declare(strict_types = 1);

namespace Drupal\egam_artist;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the artist entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class artistAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    return match($operation) {
      'view' => AccessResult::allowedIfHasPermissions($account, ['view artist', 'administer artist'], 'OR')
        ->andIf(AccessResult::allowedIf($entity->isPublished() || $account->hasPermission('administer artist')))
        ->addCacheableDependency($entity),
      'update' => AccessResult::allowedIfHasPermissions($account, ['edit artist', 'administer artist'], 'OR'),
      'delete' => AccessResult::allowedIfHasPermissions($account, ['delete artist', 'administer artist'], 'OR'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create artist', 'administer artist'], 'OR');
  }

}
