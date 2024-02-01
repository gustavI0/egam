<?php

namespace Drupal\egam_global\Handler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\egam_artwork\ArtworkInterface;
use Drupal\egam_artwork\Entity\Artwork;
use Drupal\egam_game\Entity\Game;
use Drupal\egam_game\GameInterface;
use Drupal\egam_global\Entities;
use Drupal\egam_museum\MuseumInterface;
use Drupal\egam_screenshot\Entity\Screenshot;

class RelatedContentHandler {

	const VIEW_MODE = 'teaser';

	public function __construct(protected readonly EntityTypeManagerInterface $entityTypeManager) {
	}

	public static function me(): self {
		return \Drupal::service(static::class);
	}

	public function getArtworkRelatedGames(ArtworkInterface $artwork): ?array {
		$relatedScreenshots = $this->getRelatedScreenshots($artwork);
		if (empty($relatedScreenshots)) {
			return NULL;
		}
		$relatedGames = Game::loadMultiple($this->getRelatedContentIdsFromScreenshots($relatedScreenshots, Entities::Game));
		return $this->viewRelatedContent($relatedGames);
	}

	public function getGameRelatedArtworks(GameInterface $game) {
		$relatedScreenshots = $this->getRelatedScreenshots($game);
		if (empty($relatedScreenshots)) {
			return NULL;
		}
		$relatedGames = Artwork::loadMultiple($this->getRelatedContentIdsFromScreenshots($relatedScreenshots, Entities::Artwork));
		return $this->viewRelatedContent($relatedGames);
	}

	public function getMuseumRelatedArtworks(MuseumInterface $museum) {

	}

	protected function getRelatedScreenshots(EntityInterface $entity): int|array|null {
		$relatedScreenshotsIds = \Drupal::entityQuery(Entities::Screenshot->value)->accessCheck(FALSE)->condition('field_' . $entity->bundle(), $entity->id())->execute();
		return !empty($relatedScreenshotsIds) ? Screenshot::loadMultiple($relatedScreenshotsIds) : NULL;
	}

	protected function getRelatedContentIdsFromScreenshots(array $screenshots, Entities $relatedEntity): array {
		$relatedContentIds = [];
		/* @var Screenshot $screenshot */
		foreach ($screenshots as $screenshot) {
			$relatedContentIds[] = $screenshot->get('field_' . $relatedEntity->value)->target_id;
		}
		return $relatedContentIds;
	}

	protected function viewRelatedContent(array $relatedContent): array {
		return $this->entityTypeManager->getViewBuilder($this->getEntityBundle($relatedContent))->viewMultiple($relatedContent, self::VIEW_MODE);
	}

	protected function getEntityBundle(array $relatedContent): ?string {
		$firstValue = reset($relatedContent);
		return $firstValue instanceof EntityInterface ? $firstValue->bundle() : NULL;
	}





}