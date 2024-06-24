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
		$homeMenu = '<div class="wrapper">';
		$homeMenu .= $this->buildMenuEntry(Entities::Artwork);
		$homeMenu .= '<div class="artists col1">de</div>' . $this->buildMenuEntry(Entities::Artist);
		$homeMenu .= '<div class="musea col1">conservées dans</div>' . $this->buildMenuEntry(Entities::Museum);
		$homeMenu .= '<div class="games col1">ont été référencées dans</div>' . $this->buildMenuEntry(Entities::Game);
		$homeMenu .= '</div>';
		return $homeMenu;
	}

	protected function buildMenuEntry(Entities $entity): string {
		return $this->buildCountColumn($entity) . $this->buildLinkColumn($entity);

	}

	protected function buildCountColumn(Entities $entity): string {
		return '<div class="' . $entity->getPlural() . ' col2">' . $entity->count() . '</div>';
	}

	protected function buildLinkColumn(Entities $entity): string {
		return '<div class="' . $entity->getPlural() . ' col3">' . $this->getEntityLink($entity) . '</div>';
	}

	protected function getEntityLink(Entities $entity): GeneratedLink {
		return Link::fromTextAndUrl($entity->count() > 1 ? $this->t($entity->getPlural()) : $this->t($entity->value), Url::fromRoute($entity->getCollectionRoute()))->toString();
	}

}