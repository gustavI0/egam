<?php declare(strict_types = 1);

namespace Drupal\egam_museum;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a museum entity type.
 */
interface MuseumInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
