<?php

namespace Drupal\egam_global;

use Drupal\Core\Entity\EntityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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

	public function getPlural(): string|TranslatableMarkup {
		return match ($this) {
			Entities::Artwork => 'artworks',
			Entities::Artist => 'artists',
			Entities::Game => 'games',
			Entities::Museum => 'musea',
			Entities::Screenshot => 'screenshots'
		};
	}

}