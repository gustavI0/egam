<?php declare(strict_types = 1);

namespace Drupal\egam_game\Entity;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\egam_game\GameInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the game entity class.
 *
 * @ContentEntityType(
 *   id = "game",
 *   label = @Translation("Game"),
 *   label_collection = @Translation("Games"),
 *   label_singular = @Translation("game"),
 *   label_plural = @Translation("games"),
 *   label_count = @PluralTranslation(
 *     singular = "@count games",
 *     plural = "@count games",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\egam_game\GameListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   	 "access" = "Drupal\egam_game\GameAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\egam_game\Form\GameForm",
 *       "edit" = "Drupal\egam_game\Form\GameForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" =
 *   "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *    	 "revision" = \Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider::class,
 *     },
 *   },
 *   base_table = "game",
 *   data_table = "game_field_data",
 *   revision_table = "game_revision",
 *   revision_data_table = "game_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer game",
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
 *     "collection" = "/admin/content/game",
 *     "add-form" = "/game/add",
 *     "canonical" = "/game/{game}",
 *     "edit-form" = "/game/{game}/edit",
 *     "delete-form" = "/game/{game}/delete",
 *     "delete-multiple-form" = "/admin/content/game/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.game.settings",
 * )
 */
final class Game extends RevisionableContentEntityBase implements GameInterface, EntityPublishedInterface {

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
      ->setDescription(t('The time that the game was created.'))
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
      ->setDescription(t('The time that the game was last edited.'));

    return $fields;
  }

	public function getDeveloper(): EntityInterface|EntityBase|Term|null {
		return Term::load($this->get('field_developer')->target_id);
	}

	public function getEditor(): EntityInterface|EntityBase|Term|null {
		return Term::load($this->get('field_developer')->target_id);
	}

	public function getDate(string $format = NULL): ?string {
		$date = $this->get('field_date')->value;
		return $format ? DrupalDateTime::createFromFormat('Y-m-d', $date)->format($format) : $date;
	}

	public function getFullTitle(): Link {
		return $this->toLink($this->buildTitle());
	}

	protected function buildTitle(): MarkupInterface|string {
		$title = sprintf('<div class="title"><i>%s</i></div>', $this->label());
		$date = $this->getDate('Y');
		$developer = $this->getDeveloper()->label();
		$titleString = $date ?
			sprintf('%s<div class="date">%s</div><div class="developer">%s</div>', $title, $date, $developer) :
			$title;
		return Markup::create($titleString);
	}

}
