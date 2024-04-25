<?php

namespace Drupal\egam_global\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\GeneratedLink;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\egam_global\Entities;
use Drupal\egam_global\Handler\HomeCoverHandler;

class HomeController extends ControllerBase {

	use StringTranslationTrait;

	const TEMPLATE = 'home';

	public function __construct(protected readonly HomeCoverHandler $homeCoverHandler) {
	}

	public function render(): array {
		return [
			'#theme' => self::TEMPLATE,
			'#cover_src' => $this->homeCoverHandler->getRandomCover() ?? NULL,
			'#title' => \Drupal::config('system.site')->get('name'),
			'#home_menu' => $this->buildHomeMenuFr(),
			'#cache' => ['max-age' => 0]
		];
	}

	protected function buildHomeMenuFr(): string {
		/* @var \Drupal\egam_global\Entities[] $selectedEntities */
		$selectedEntities = [Entities::Artist, Entities::Museum, Entities::Game];
		$countAndPath = [];
		foreach ($selectedEntities as $entity) {
			$countAndPath[$entity->value] = sprintf('<span class="count">%s %s</span>', $entity->count(), $this->getEntityLink($entity));
		}
		return '<div class="wrapper">
						<div class="artworks col2">' . Entities::Artwork->count() . ' œuvres</div>
            <div class="artists col1">de</div>
            <div class="artists col2">' . $countAndPath[Entities::Artist->value] . '</div>
            <div class="musea col1">conservées dans</div>
            <div class="musea col2">' . $countAndPath[Entities::Museum->value] . '</div>
            <div class="games col1">ont été référencées dans</div>
            <div class="games col2">' . $countAndPath[Entities::Game->value] . '</div>
            </div>';
	}

	protected function getEntityLink(Entities $entity): GeneratedLink {
		return Link::fromTextAndUrl($entity->count() > 1 ? $this->t($entity->getPlural()) : $this->t($entity->value), Url::fromRoute($entity->getCollectionRoute()))->toString();
	}

}