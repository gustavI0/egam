<?php

namespace Drupal\egam_artwork;

/**
 * Names shared by the artwork type taxonomy, its terms and the painting fields.
 */
final class ArtworkTypes {

  public const VOCABULARY = 'artwork_type';

  public const TYPE_FIELD = 'field_artwork_type';

  /**
   * Fields that only make sense for a painting.
   */
  public const PAINTING_FIELDS = ['field_school', 'field_york_project'];

  public const PAINTING = 'Peinture';

  /**
   * Term names, in display order.
   */
  public const NAMES = [self::PAINTING, 'Sculpture', 'Dessins', 'Gravure', "Objet d'art"];

}
