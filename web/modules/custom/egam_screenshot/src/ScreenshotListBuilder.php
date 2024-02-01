<?php declare(strict_types = 1);

namespace Drupal\egam_screenshot;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\egam_artwork\Entity\Artwork;
use Drupal\egam_game\Entity\Game;

/**
 * Provides a list controller for the screenshot entity type.
 */
final class ScreenshotListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Label');
    $header['artwork'] = $this->t('Artwork');
    $header['game'] = $this->t('Game');
    $header['status'] = $this->t('Status');
    $header['uid'] = $this->t('Author');
    $header['created'] = $this->t('Created');
    $header['changed'] = $this->t('Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\egam_screenshot\ScreenshotInterface $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->toLink();
    $row['artwork'] = Artwork::load($entity->get('field_artwork')->target_id)->toLink();
    $row['game'] = Game::load($entity->get('field_game')->target_id)->toLink();
    $row['status'] = $entity->get('status')->value ? $this->t('Enabled') : $this->t('Disabled');
    $username_options = [
      'label' => 'hidden',
      'settings' => ['link' => $entity->get('uid')->entity->isAuthenticated()],
    ];
    $row['uid']['data'] = $entity->get('uid')->view($username_options);
    $row['created']['data'] = $entity->get('created')->view(['label' => 'hidden']);
    $row['changed']['data'] = $entity->get('changed')->view(['label' => 'hidden']);
    return $row + parent::buildRow($entity);
  }

}
