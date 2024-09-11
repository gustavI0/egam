<?php

namespace Drupal\egam_global\Handler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\egam_artwork\ArtworkInterface;
use Drupal\egam_game\GameInterface;
use Drupal\egam_global\Entities;
use Drupal\egam_screenshot\ScreenshotInterface;

class RelatedContentHandler {

	const TEASER_VIEW_MODE = 'teaser';

	public bool $hasMultipleRelatedContent;

	public function __construct(protected readonly EntityTypeManagerInterface $entityTypeManager) {
	}

	public static function me(): self {
		return \Drupal::service(static::class);
	}

	/**
	 * @throws \Drupal\Core\Entity\EntityMalformedException
	 */
	public function viewContent(EntityInterface $entity, Entities $relatedEntity): ?array {
		$relatedContent = $this->getContent($entity, $relatedEntity);
		$this->hasMultipleRelatedContent = count($relatedContent) > 1;
		return $relatedContent ?
			$this->entityTypeManager->getViewBuilder($this->getContentEntityBundle($relatedContent))->viewMultiple($relatedContent, self::TEASER_VIEW_MODE) :
			NULL;
	}

	protected function getContent(EntityInterface $entity, Entities $relatedEntity): ?array {
		$relatedContentIds = $this->getContentIds($entity, $relatedEntity);
		if (empty($relatedContentIds)) return NULL;
		return $relatedEntity->loadMultiple($relatedContentIds);
	}

	/**
	 * @throws \Drupal\Core\Entity\EntityMalformedException
	 */
	protected function getContentEntityBundle(array $relatedContent): string {
		$entity = reset($relatedContent);
		return $entity instanceof EntityInterface ? $entity->bundle() : throw new EntityMalformedException();
	}

	protected function getContentIds(EntityInterface $entity, Entities $relatedEntity): int|array {
		return \Drupal::entityQuery($relatedEntity->value)
			->accessCheck(FALSE)
			->condition('field_' . $entity->bundle(), $entity->id())
			->sort($this->getSortingField($entity))
			->execute();
	}

	protected function getSortingField(EntityInterface $entity): ?string {
		return match ($entity->bundle()) {
			Entities::Game->value => 'field_artwork.entity:artwork.field_sorting_name',
			Entities::Artwork->value => 'field_game.entity:game.field_sorting_name',
			Entities::Screenshot->value, Entities::Artist->value, Entities::Museum->value  => 'label'
		};
	}

}