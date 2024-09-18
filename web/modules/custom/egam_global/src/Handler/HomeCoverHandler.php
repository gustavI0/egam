<?php

namespace Drupal\egam_global\Handler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\egam_global\Form\GlobalSettingsForm;
use Drupal\file\FileInterface;
use Drupal\file\FileStorage;
use Drupal\image\ImageStyleStorage;
use Drupal\media\MediaInterface;
use Drupal\media\MediaStorage;

class HomeCoverHandler {

	protected ImmutableConfig $config;

	protected MediaStorage $mediaStorage;

	protected FileStorage $fileStorage;

	protected ImageStyleStorage $imageStyle;

	public function __construct(
		protected readonly ConfigFactoryInterface $configFactory,
		protected readonly EntityTypeManagerInterface $entityTypeManager,
		protected readonly FileUrlGeneratorInterface $fileUrlGenerator) {
		$this->config = $this->configFactory->get(GlobalSettingsForm::EDITABLE_CONFIG_NAME);
		$this->mediaStorage = $this->entityTypeManager->getStorage('media');
		$this->fileStorage = $this->entityTypeManager->getStorage('file');
		$this->imageStyle = $this->entityTypeManager->getStorage('image_style');
	}

	public static function me(): self {
		return \Drupal::service(static::class);
	}

	public function getRandomCover(): ?string {
		$configuredHomeCovers = $this->getConfiguredHomeCovers();
		if (empty($configuredHomeCovers)) return NULL;

		$randomCoverKey = $this->randomize($configuredHomeCovers);

		/* @var \Drupal\media\MediaInterface $media */
		$media = $this->mediaStorage->load($configuredHomeCovers[$randomCoverKey]);
		if (!$media) return NULL;

		$file = $this->getFile($media);
		if (!$file) return NULL;

		/* @var \Drupal\image\Entity\ImageStyle $style */
		$style = $this->imageStyle->load('webp');
		$styledImgUri = $style->buildUri($file->getFileUri());
		if (!$styledImgUri) return $file->createFileUrl();

		return $this->fileUrlGenerator->generate($styledImgUri)->toString() ?? $file->createFileUrl();
	}

	protected function getConfiguredHomeCovers(): ?array {
		$homeCoversIds =  $this->config->get(GlobalSettingsForm::BACKGROUND_MEDIA);
		if (empty($homeCoversIds)) {
			return NULL;
		}
		return explode(',', $homeCoversIds);
	}

	protected function randomize(array $configuredHomeCovers): array|int|string {
		return array_rand($configuredHomeCovers);
	}

	public function getFile(MediaInterface $media): ?FileInterface {
		/* @var \Drupal\file\FileInterface $file */
		return $this->fileStorage->load($media->getSource()->getSourceFieldValue($media));
	}

}