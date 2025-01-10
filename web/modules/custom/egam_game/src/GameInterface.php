<?php declare(strict_types = 1);

namespace Drupal\egam_game;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Link;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a game entity type.
 */
interface GameInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

	public function getFullTitle(bool $asLink): MarkupInterface|Link;

}
