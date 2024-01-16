<?php

namespace Drupal\egam_global\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\egam_global\Entities;

class HomeController extends ControllerBase {

	use StringTranslationTrait;

	public function render(): array {

		return [
			'#theme' => 'home',
			'#title' => \Drupal::config('system.site')->get('name'),
			'#markup' => 'markup',
			'#count_artworks' => Entities::Artwork->count(),
			'#count_artists' => Entities::Artist->count(),
			'#count_musea' => Entities::Museum->count(),
			'#count_games' => Entities::Game->count(),
		];
	}

}