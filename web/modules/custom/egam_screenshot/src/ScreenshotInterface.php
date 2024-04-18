<?php declare(strict_types = 1);

namespace Drupal\egam_screenshot;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\egam_artwork\ArtworkInterface;
use Drupal\egam_game\GameInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a screenshot entity type.
 */
interface ScreenshotInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

	public function getReferencedGame(): GameInterface;

	public function getReferencedArtwork(): ArtworkInterface;

	public function getContextualizedTitle(ContentEntityInterface $entity): string;
}
