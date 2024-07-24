<?php

namespace Drupal\egam_global\Handler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\egam_artwork\ArtworkInterface;
use Drupal\egam_game\GameInterface;
use Drupal\egam_global\Entities;
use Drupal\egam_screenshot\ScreenshotInterface;

class RelatedContentHandler {

	const VIEW_MODE = 'teaser';

	public function __construct(protected readonly EntityTypeManagerInterface $entityTypeManager) {
	}

	public static function me(): self {
		return \Drupal::service(static::class);
	}

	public function viewRelatedContent(EntityInterface $entity, Entities $relatedEntity): ?array {
		$relatedContent = $this->getRelatedContent($entity, $relatedEntity);
		return $relatedContent ? $this->entityTypeManager->getViewBuilder($this->getEntityBundle($relatedContent))->viewMultiple($relatedContent, self::VIEW_MODE) : NULL;
	}

	protected function getRelatedContent(EntityInterface $entity, Entities $relatedEntity): ?array {
		$relatedContentIds = $this->getRelatedContentIds($entity, $relatedEntity);
		if (empty($relatedContentIds)) return NULL;
		return $relatedEntity->loadMultiple($relatedContentIds);
	}

	protected function sortRelatedContent() {

	}

	protected function getRelatedContentIds(EntityInterface $entity, Entities $relatedEntity): ?array {
		return $this->isFromScreenshots($entity, $relatedEntity) ?
			$this->getRelatedContentIdsFromScreenshots($entity, $relatedEntity) :
			$this->getDefaultRelatedContentIds($entity, $relatedEntity);
	}

	protected function isFromScreenshots(EntityInterface $entity, Entities $relatedEntity): bool {
		return ($entity instanceof ArtworkInterface && $relatedEntity == Entities::Game) || ($entity instanceof GameInterface && $relatedEntity == Entities::Artwork);
	}

	protected function getEntityBundle(array $relatedContent): ?string {
		$firstValue = reset($relatedContent);
		return $firstValue instanceof EntityInterface ? $firstValue->bundle() : NULL;
	}

	protected function getRelatedContentIdsFromScreenshots(EntityInterface $entity, Entities $relatedEntity): ?array {
		$relatedScreenshots = $this->getRelatedContent($entity, Entities::Screenshot);
		if (empty($relatedScreenshots)) {
			return NULL;
		}
		$relatedContentIds = [];
		/* @var ScreenshotInterface $screenshot */
		foreach ($relatedScreenshots as $screenshot) {
			$relatedContentIds[] = $screenshot->get('field_' . $relatedEntity->value)->target_id;
		}
		return $relatedContentIds;
	}

	protected function getDefaultRelatedContentIds(EntityInterface $entity, Entities $relatedEntity): int|array {
		return \Drupal::entityQuery($relatedEntity->value)
			->accessCheck(FALSE)
			->condition('field_' . $entity->bundle(), $entity->id())
			->sort('label')
			->execute();
	}

}