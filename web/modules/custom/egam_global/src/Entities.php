<?php

namespace Drupal\egam_global;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\egam_artist\Entity\Artist;
use Drupal\egam_artwork\Entity\Artwork;
use Drupal\egam_game\Entity\Game;
use Drupal\egam_museum\Entity\Museum;
use Drupal\egam_screenshot\Entity\Screenshot;

enum Entities: string {
	case Artwork = 'artwork';
	case Artist = 'artist';
	case Game = 'game';
	case Museum = 'museum';
	case Screenshot = 'screenshot';

	public function count(): int|array {
		return \Drupal::entityQuery($this->value)->accessCheck(FALSE)->count()->execute();
	}

	public function getCollectionRoute(): string {
		return 'view.' . $this->getPlural() . '.grid';
	}

	public function getCanonicalRoute(): string {
		return 'entity.' . $this->value . '.canonical';
	}

	public function getPlural(): string|TranslatableMarkup {
		return match ($this) {
			Entities::Artwork => 'artworks',
			Entities::Artist => 'artists',
			Entities::Game => 'games',
			Entities::Museum => 'musea',
			Entities::Screenshot => 'screenshots'
		};
	}

	public function loadMultiple(array $ids): array {
		return match ($this) {
			Entities::Game => Game::loadMultiple($ids),
			Entities::Artist => Artist::loadMultiple($ids),
			Entities::Artwork => Artwork::loadMultiple($ids),
			Entities::Museum => Museum::loadMultiple($ids),
			Entities::Screenshot => Screenshot::loadMultiple($ids),
		};
	}

}