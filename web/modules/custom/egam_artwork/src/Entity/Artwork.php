<?php declare(strict_types = 1);

namespace Drupal\egam_artwork\Entity;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\GeneratedLink;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\egam_artist\Entity\Artist;
use Drupal\egam_artist\Entity\ArtistInterface;
use Drupal\egam_artwork\Entity\ArtworkInterface;
use Drupal\egam_museum\Entity\Museum;
use Drupal\egam_museum\MuseumInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the artwork entity class.
 *
 * @ContentEntityType(
 *   id = "artwork",
 *   label = @Translation("Artwork"),
 *   label_collection = @Translation("Artworks"),
 *   label_singular = @Translation("artwork"),
 *   label_plural = @Translation("artworks"),
 *   label_count = @PluralTranslation(
 *     singular = "@count artworks",
 *     plural = "@count artworks",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\egam_artwork\ArtworkListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   	 "access" = "Drupal\egam_artwork\ArtworkAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\egam_artwork\Form\ArtworkForm",
 *       "edit" = "Drupal\egam_artwork\Form\ArtworkForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" =
 *   "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "artwork",
 *   data_table = "artwork_field_data",
 *   revision_table = "artwork_revision",
 *   revision_data_table = "artwork_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer artwork",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "published" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   links = {
 *     "collection" = "/admin/content/artwork",
 *     "add-form" = "/artwork/add",
 *     "canonical" = "/artwork/{artwork}",
 *     "edit-form" = "/artwork/{artwork}/edit",
 *     "delete-form" = "/artwork/{artwork}/delete",
 *     "delete-multiple-form" = "/admin/content/artwork/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.artwork.settings",
 * )
 */
final class Artwork extends RevisionableContentEntityBase implements ArtworkInterface, EntityPublishedInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;
  use EntityPublishedTrait;

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

    // Override status field from EntityPublishedTrait to ensure proper configuration.
    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Published'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Published')
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
          'format' => 'custom',
          'format_custom_false' => 'Unpublished',
          'format_custom_true' => 'Published',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Title'))
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
      ->setDescription(t('The time that the artwork was created.'))
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
      ->setDescription(t('The time that the artwork was last edited.'));

    return $fields;
  }

	public function getDate(): ?string {
		return $this->get('field_date')->value;
	}

	public function getArtist(): ArtistInterface {
		return Artist::load($this->get('field_artist')->target_id);
	}

	public function getMuseum(): MuseumInterface {
		return Museum::load($this->get('field_museum')->target_id);
	}

	public function getThumbnail(): ?MediaInterface {
		$mid = !$this->get('field_thumbnail')->isEmpty() ? $this->get('field_thumbnail')->target_id : NULL;
		return $mid ? Media::load($mid) : NULL;
	}

	public function getFullTitle(bool $withArtist = TRUE, bool $asLink = TRUE): string|Link {
		return $asLink ? $this->toLink($this->buildTitle($withArtist)) : $this->buildTitle($withArtist);
	}

	public function getFullArtist(bool $asLink = FALSE): string|GeneratedLink {
		$prefix = $this->getArtistPrefix();
		$artist = $this->getArtist();
		return $prefix ? $asLink ?
			sprintf('%s %s', $artist->toLink()->toString(), $this->buildArtistPrefix($prefix)) :
			sprintf('%s %s', $artist->label(), $this->buildArtistPrefix($prefix)) : ($asLink ?
			$artist->toLink()->toString() :
			$this->getArtist()->label());
	}

	public function getArtistPrefix(): ?string {
		$prefixValue = $this->get('field_artist_prefix')->value;
		if (!$prefixValue) {
			return NULL;
		}
		return $this->get('field_artist_prefix')->getFieldDefinition()->getSettings()['allowed_values'][$prefixValue];
	}

	protected function buildArtistPrefix(string $prefix): string {
		return sprintf('(%s)', mb_strtolower($prefix));
	}

	protected function buildTitle(bool $withArtist): MarkupInterface|string {
		$title = sprintf('<div class="title"><i>%s</i></div>', $this->label());
		$date = $this->getDate();
		$artist = $this->getFullArtist();
		$fullTitle =  $withArtist ? $date ?
			sprintf('%s<div class="date">%s</div><div class="artist">%s</div>', $title, $date, $artist) :
			sprintf('%s%s', $title, $artist) : ($date ?
			sprintf('%s%s', $title, $date) :
			sprintf('%s', $title));
		return Markup::create($fullTitle);
	}

	public function getFullLocationAsLink(): string|GeneratedLink {
		$museum = $this->getMuseum();
		return $museum->getLocation() ?
			sprintf('%s, %s', $museum->toLink()->toString(), $museum->getLocation()) :
			$museum->toLink()->toString();
	}

}
