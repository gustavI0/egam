<?php declare(strict_types = 1);

namespace Drupal\egam_screenshot;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a screenshot entity type.
 */
interface ScreenshotInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
