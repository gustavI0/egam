<?php

namespace Drupal\egam_artwork\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\egam_artwork\ArtworkTypes;
use Drupal\egam_artwork\Entity\ArtworkInterface;
use Drupal\egam_artwork\Form\ArtworkForm;

/**
 * Keeps the painting-only fields (school, York Project) tied to the painting type.
 */
class ArtworkTypeHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Shows the painting fields only while the painting type is selected.
   *
   * Without a painting term (not created yet) the fields stay visible.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!$form_state->getFormObject() instanceof ArtworkForm) {
      return;
    }
    $paintingId = $this->paintingTermId();
    if ($paintingId === NULL) {
      return;
    }
    $visibleForPainting = [
      // A string, like the value of the <select> it is compared with.
      ':input[name="' . ArtworkTypes::TYPE_FIELD . '"]' => ['value' => (string) $paintingId],
    ];
    foreach (ArtworkTypes::PAINTING_FIELDS as $field) {
      if (isset($form[$field])) {
        $form[$field]['#states']['visible'] = $visibleForPainting;
      }
    }
  }

  /**
   * Empties the painting fields when the artwork has a type other than painting.
   *
   * They are hidden in the form, so a stale value would otherwise stay in the
   * database and in the exports. Nothing is cleared for an artwork without a
   * type (a script may only set these fields), nor while the painting term
   * cannot be found: the form then shows the fields, so they must be kept.
   */
  #[Hook('artwork_presave')]
  public function presave(ArtworkInterface $artwork): void {
    if (!$artwork->hasField(ArtworkTypes::TYPE_FIELD)) {
      return;
    }
    $typeId = $artwork->get(ArtworkTypes::TYPE_FIELD)->target_id;
    if ($typeId === NULL) {
      return;
    }
    $paintingId = $this->paintingTermId();
    // Same term id as the form uses, so both always agree on what a painting is.
    if ($paintingId === NULL || (string) $typeId === (string) $paintingId) {
      return;
    }
    foreach (ArtworkTypes::PAINTING_FIELDS as $field) {
      if ($artwork->hasField($field) && !$artwork->get($field)->isEmpty()) {
        $artwork->set($field, NULL);
      }
    }
  }

  private function paintingTermId(): string|int|null {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => ArtworkTypes::VOCABULARY,
      'name' => ArtworkTypes::PAINTING,
    ]);
    return $terms ? array_key_first($terms) : NULL;
  }

}
