<?php declare(strict_types = 1);

namespace Drupal\egam_game;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the game entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class GameAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    return match($operation) {
      'view' => AccessResult::allowedIfHasPermissions($account, ['view game', 'administer game'], 'OR')
        ->andIf(AccessResult::allowedIf($entity->isPublished() || $account->hasPermission('administer game')))
        ->addCacheableDependency($entity),
      'update' => AccessResult::allowedIfHasPermissions($account, ['edit game', 'administer game'], 'OR'),
      'delete' => AccessResult::allowedIfHasPermissions($account, ['delete game', 'administer game'], 'OR'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create game', 'administer game'], 'OR');
  }

}
