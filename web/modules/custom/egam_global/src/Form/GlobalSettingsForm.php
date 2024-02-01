<?php

namespace Drupal\egam_global\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class GlobalSettingsForm extends ConfigFormBase {

	const FORM_ID = 'global_settings_form';

	const EDITABLE_CONFIG_NAME = 'egam.global.settings';

	const HOME = 'home';

	const BACKGROUND_MEDIA = 'background_media';

	/**
	 * @inheritDoc
	 */
	protected function getEditableConfigNames(): array {
		return [self::EDITABLE_CONFIG_NAME];
	}

	/**
	 * @inheritDoc
	 */
	public function getFormId(): string {
		return self::FORM_ID;
	}

	/**
	 * @inheritDoc
	 */
	public function buildForm(array $form, FormStateInterface $form_state): array {

		$config = $this->config(self::EDITABLE_CONFIG_NAME);

		$form[self::HOME] = [
			'#type' => 'fieldset',
		];

		$form[self::HOME]['title'] = [
			'#type' => 'item',
			'#markup' => $this->t('Homepage')
		];

		$form[self::HOME][self::BACKGROUND_MEDIA] = [
			'#type' => 'media_library',
			'#title' => $this->t('Background homepage'),
			'#description' => $this->t('Vous pouvez sélectionner jusqu\'à 10 images'),
			'#allowed_bundles' => ['image'],
			'#default_value' => $config->get(self::BACKGROUND_MEDIA) ?? NULL,
			'#cardinality' => 10,
		];

		return parent::buildForm($form, $form_state);
	}

	/**
	 * @inheritDoc
	 */
	public function submitForm(array &$form, FormStateInterface $form_state): void {
		$homeValues = $form_state->getValues();

		$this
			->config(self::EDITABLE_CONFIG_NAME)
			->set(self::BACKGROUND_MEDIA, $homeValues[self::BACKGROUND_MEDIA] ?? NULL)
			->save();

		parent::submitForm($form, $form_state);
	}



}