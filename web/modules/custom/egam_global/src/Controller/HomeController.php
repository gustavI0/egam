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
		];
	}

	protected function buildHomeMenuFr(): string {
		/* @var \Drupal\egam_global\Entities[] $selectedEntities */
		$selectedEntities = [Entities::Artwork, Entities::Artist, Entities::Museum, Entities::Game];
		$countAndPath = [];
		foreach ($selectedEntities as $entity) {
			$countAndPath[$entity->value] = $entity->count() . ' ' . $this->getEntityLink($entity);
		}
		return '<p>' . $countAndPath[Entities::Artwork->value] . '</p>
            <p>de ' . $countAndPath[Entities::Artist->value] . '</p>
            <p>conservées dans ' . $countAndPath[Entities::Museum->value] . '</p>
            <p>ont été référencées dans ' . $countAndPath[Entities::Game->value] . '</p>';
	}

	protected function getEntityLink(Entities $entity): GeneratedLink {
		return Link::fromTextAndUrl($entity->count() > 1 ? $this->t($entity->getPlural()) : $this->t($entity->value), Url::fromRoute($entity->getCollectionRoute()))->toString();
	}

}