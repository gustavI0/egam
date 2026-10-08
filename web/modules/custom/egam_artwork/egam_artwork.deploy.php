<?php

/**
 * @file
 * Deploy hooks, run by `drush deploy` after the configuration import.
 */

use Drupal\egam_artwork\ArtworkTypes;
use Drupal\taxonomy\Entity\Term;

/**
 * Creates the artwork type terms that do not exist yet.
 *
 * Terms are content, so the configuration import does not carry them.
 */
function egam_artwork_deploy_create_artwork_types(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $created = [];
  foreach (ArtworkTypes::NAMES as $weight => $name) {
    $existing = $storage->loadByProperties(['vid' => ArtworkTypes::VOCABULARY, 'name' => $name]);
    if ($existing) {
      continue;
    }
    Term::create(['vid' => ArtworkTypes::VOCABULARY, 'name' => $name, 'weight' => $weight])->save();
    $created[] = $name;
  }
  return $created ? 'Created artwork types: ' . implode(', ', $created) . '.' : 'All artwork types already exist.';
}
