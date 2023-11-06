<?php

namespace Drupal\egam_artist\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an artist entity type.
 */
interface ArtistInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
