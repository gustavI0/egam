<?php declare(strict_types = 1);

namespace Drupal\egam_artwork;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\egam_artist\Entity\Artist;
use Drupal\egam_artist\Entity\ArtistInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an artwork entity type.
 */
interface ArtworkInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

	public function getDate(): ?string;

	public function getArtist(): ArtistInterface;

}
