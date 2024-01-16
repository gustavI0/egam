<?php

namespace Drupal\egam_global;

enum Entities: string {
	case Artwork = 'artwork';
	case Artist = 'artist';
	case Game = 'game';
	case Museum = 'museum';
	case Screenshot = 'screenshot';

	public function count(): int|array {
		return \Drupal::entityQuery($this->value)->accessCheck(FALSE)->count()->execute();
	}

}