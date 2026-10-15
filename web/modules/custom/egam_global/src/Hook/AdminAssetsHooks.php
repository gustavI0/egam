<?php

namespace Drupal\egam_global\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;

/**
 * Attaches the stylesheet that adapts the admin theme to the EGAM screens.
 */
class AdminAssetsHooks {

  public function __construct(private readonly AdminContext $adminContext) {}

  #[Hook('page_attachments')]
  public function attachAdminStyles(array &$attachments): void {
    // The answer depends on the page: it must not leak to the front end.
    $attachments['#cache']['contexts'][] = 'route';
    if ($this->adminContext->isAdminRoute()) {
      $attachments['#attached']['library'][] = 'egam_global/admin';
    }
  }

}
