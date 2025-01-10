<?php declare(strict_types = 1);

namespace Drupal\egam_screenshot\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\egam_artwork\ArtworkInterface;
use Drupal\egam_artwork\Entity\Artwork;
use Drupal\egam_game\Entity\Game;
use Drupal\egam_game\GameInterface;
use Drupal\egam_global\Entities;
use Drupal\egam_screenshot\ScreenshotInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the screenshot entity class.
 *
 * @ContentEntityType(
 *   id = "screenshot",
 *   label = @Translation("Screenshot"),
 *   label_collection = @Translation("Screenshots"),
 *   label_singular = @Translation("screenshot"),
 *   label_plural = @Translation("screenshots"),
 *   label_count = @PluralTranslation(
 *     singular = "@count screenshots",
 *     plural = "@count screenshots",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\egam_screenshot\ScreenshotListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   	 "access" = "Drupal\egam_screenshot\ScreenshotAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\egam_screenshot\Form\ScreenshotForm",
 *       "edit" = "Drupal\egam_screenshot\Form\ScreenshotForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" =
 *   "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "screenshot",
 *   data_table = "screenshot_field_data",
 *   revision_table = "screenshot_revision",
 *   revision_data_table = "screenshot_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer screenshot",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   links = {
 *     "collection" = "/admin/content/screenshot",
 *     "add-form" = "/screenshot/add",
 *     "canonical" = "/screenshot/{screenshot}",
 *     "edit-form" = "/screenshot/{screenshot}/edit",
 *     "delete-form" = "/screenshot/{screenshot}/delete",
 *     "delete-multiple-form" = "/admin/content/screenshot/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.screenshot.settings",
 * )
 */
final class Screenshot extends RevisionableContentEntityBase implements ScreenshotInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;
	use StringTranslationTrait;

	const FIELD_ARTWORK = 'field_artwork';

	const FIELD_GAME = 'field_game';

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Notes'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the screenshot was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the screenshot was last edited.'));

    return $fields;
  }

	public function getReferencedGame(): GameInterface {
		return Game::load($this->get(self::FIELD_GAME)->target_id);
	}

	public function getReferencedArtwork(): ArtworkInterface {
		return Artwork::load($this->get(self::FIELD_ARTWORK)->target_id);
	}

	public function getContextualizedTitle(ContentEntityInterface $entity): TranslatableMarkup|string|Link {
		return match($entity->bundle()) {
			Entities::Artwork->value => $this->getReferencedGame()->getFullTitle(),
			Entities::Game->value => $this->getTitleForGameContext()
		};
	}

	public function hasMultipleRelatedArtworks(): bool {
		return $this->get(self::FIELD_ARTWORK)->count() > 1;
	}

	protected function getTitleForGameContext(): TranslatableMarkup|string|Link {
		return $this->hasMultipleRelatedArtworks() ? $this->t('Multiple artworks') : $this->getReferencedArtwork()->getFullTitle();
	}

	public function getSearchResultTitle(): Link {
		$title = '<i>' . $this->getReferencedArtwork()->label() . '</i> ';
		$title .= $this->t('dans');
		$title .= ' <i>' . $this->getReferencedGame()->label() . '</i>';
		return $this->toLink(Markup::create($title));
	}

}
