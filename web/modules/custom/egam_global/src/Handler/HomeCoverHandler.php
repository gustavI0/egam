<?php

namespace Drupal\egam_global\Handler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\egam_global\Form\GlobalSettingsForm;
use Drupal\file\Entity\File;
use Drupal\file\FileStorage;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media\MediaStorage;

class HomeCoverHandler {

	protected ImmutableConfig $config;

	protected MediaStorage $mediaStorage;

	protected FileStorage $fileStorage;

	public function __construct(protected readonly ConfigFactoryInterface $configFactory, protected readonly EntityTypeManagerInterface $entityTypeManager) {
		$this->config = $this->configFactory->get(GlobalSettingsForm::EDITABLE_CONFIG_NAME);
		$this->mediaStorage = $this->entityTypeManager->getStorage('media');
		$this->fileStorage = $this->entityTypeManager->getStorage('file');
	}

	public static function me(): self {
		return \Drupal::service(static::class);
	}

	public function getRandomCover(): ?string {
		$configuredHomeCovers = $this->getConfiguredHomeCovers();
		if (empty($configuredHomeCovers)) {
			return NULL;
		}
		$randomCoverKey = array_rand($configuredHomeCovers);
		$media = $this->mediaStorage->load($configuredHomeCovers[$randomCoverKey]);
		return  $media ? $this->getImgSource($media) : NULL;
	}

	protected function getConfiguredHomeCovers(): ?array {
		$homeCoversIds =  $this->config->get(GlobalSettingsForm::BACKGROUND_MEDIA);
		if (empty($homeCoversIds)) {
			return NULL;
		}
		return explode(',', $homeCoversIds);
	}

	public function getImgSource(MediaInterface $media): string {
		return $this->fileStorage->load($media->getSource()->getSourceFieldValue($media))->createFileUrl();
	}

}