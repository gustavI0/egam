<?php

namespace Drupal\egam_export\Export;

use Drupal\egam_global\Entities;

/**
 * One exported cell: text, plus an optional link to another sheet or a URL.
 */
final class ExportCell {

  public function __construct(
    public readonly string $text,
    public readonly ?string $csvText = NULL,
    public readonly ?Entities $linkEntity = NULL,
    public readonly string|int|null $linkId = NULL,
    public readonly ?string $externalUrl = NULL,
  ) {}

  /**
   * Text to write in a CSV file.
   */
  public function csv(): string {
    return $this->csvText ?? $this->text;
  }

}
