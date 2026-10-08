<?php

namespace Drupal\egam_export\Export;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\egam_global\Entities;
use Drupal\options\Plugin\Field\FieldType\ListItemBase;

/**
 * Turns the values of a field into one exportable cell.
 */
class FieldValueNormalizer {

  /**
   * First characters that make a spreadsheet read a cell as a formula.
   */
  private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

  /**
   * Types whose values are numbers or dates, never read as formulas.
   */
  private const NON_TEXT_TYPES = [
    'boolean', 'integer', 'decimal', 'float', 'created', 'changed', 'timestamp', 'datetime',
  ];

  public function normalize(FieldItemListInterface $items): ExportCell {
    $type = $items->getFieldDefinition()->getType();
    if ($type === 'entity_reference') {
      return $this->normalizeReference($items);
    }
    $texts = [];
    $csvTexts = [];
    foreach ($items as $item) {
      $text = $this->normalizeItem($type, $item);
      $texts[] = $text;
      $csvTexts[] = in_array($type, self::NON_TEXT_TYPES, TRUE) ? $text : $this->sanitize($text);
    }
    return new ExportCell(implode("\n", $texts), implode("\n", $csvTexts));
  }

  /**
   * Prefixes values that a spreadsheet would execute as a formula.
   *
   * Only needed for CSV: the XLSX writer stores every text as a string.
   */
  public function sanitize(string $value): string {
    if ($value !== '' && in_array($value[0], self::FORMULA_TRIGGERS, TRUE)) {
      return "'" . $value;
    }
    return $value;
  }

  private function normalizeItem(string $type, FieldItemInterface $item): string {
    return match ($type) {
      'boolean' => $item->value ? '1' : '0',
      'integer', 'decimal', 'float' => (string) $item->value,
      'created', 'changed', 'timestamp' => gmdate(\DATE_ATOM, (int) $item->value),
      'datetime' => (string) $item->value,
      'list_string', 'list_integer', 'list_float' => $this->listLabel($item),
      'text', 'text_long', 'text_with_summary' => $this->plainText((string) $item->value),
      'link' => (string) $item->uri,
      default => $item->getString(),
    };
  }

  private function listLabel(FieldItemInterface $item): string {
    $key = (string) $item->value;
    if ($item instanceof ListItemBase) {
      return (string) ($item->getPossibleOptions()[$key] ?? $key);
    }
    return $key;
  }

  private function plainText(string $html): string {
    $withBreaks = preg_replace('#<(br\s*/?|/p)>#i', "\n", $html);
    return trim(Html::decodeEntities(strip_tags($withBreaks)));
  }

  private function normalizeReference(FieldItemListInterface $items): ExportCell {
    $targetType = (string) $items->getFieldDefinition()->getSetting('target_type');
    $linkEntity = Entities::tryFrom($targetType);
    $texts = [];
    $csvTexts = [];
    $linkId = NULL;
    foreach ($items as $item) {
      $id = $item->target_id;
      $entity = $item->entity;
      if ($entity === NULL) {
        // The target was deleted: keep its id, nothing to link to.
        $texts[] = $csvTexts[] = '#' . $id;
        continue;
      }
      $label = (string) $entity->label();
      $texts[] = $label;
      $csvLabel = $this->sanitize($label);
      $csvTexts[] = $targetType === 'user' ? $csvLabel : $csvLabel . ' (#' . $id . ')';
      if ($linkEntity !== NULL && $linkId === NULL) {
        $linkId = $id;
      }
    }
    return new ExportCell(
      implode("\n", $texts),
      implode("\n", $csvTexts),
      $linkId !== NULL ? $linkEntity : NULL,
      $linkId,
    );
  }

}
