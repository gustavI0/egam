<?php

namespace Drupal\egam_export\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\egam_export\Export\CsvExporter;
use Drupal\egam_export\Export\ExportException;
use Drupal\egam_export\Export\ExportTableBuilder;
use Drupal\egam_export\Export\XlsxExporter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Admin page and downloads for the EGAM data export.
 */
class ExportController extends ControllerBase {

  private const XLSX_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

  public function __construct(
    private readonly ExportTableBuilder $tableBuilder,
    private readonly CsvExporter $csvExporter,
    private readonly XlsxExporter $xlsxExporter,
    private readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ExportTableBuilder::class),
      $container->get(CsvExporter::class),
      $container->get(XlsxExporter::class),
      $container->get('datetime.time'),
    );
  }

  public function page(): array {
    $attributes = ['class' => ['button', 'button--primary']];
    return [
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Download every artwork, artist, game, museum and screenshot, including unpublished content. Images are not included.'),
      ],
      'csv' => [
        '#type' => 'link',
        '#title' => $this->t('Download CSV (zip)'),
        '#url' => Url::fromRoute('egam_export.csv'),
        '#attributes' => $attributes,
      ],
      'xlsx' => [
        '#type' => 'link',
        '#title' => $this->t('Download XLSX'),
        '#url' => Url::fromRoute('egam_export.xlsx'),
        '#attributes' => $attributes,
      ],
    ];
  }

  public function downloadCsv(): Response {
    return $this->download(
      fn (): string => $this->csvExporter->export($this->tableBuilder->buildAll()),
      'zip',
      'application/zip',
    );
  }

  public function downloadXlsx(): Response {
    return $this->download(
      fn (): string => $this->xlsxExporter->export($this->tableBuilder->buildAll()),
      'xlsx',
      self::XLSX_CONTENT_TYPE,
    );
  }

  /**
   * @param callable(): string $generate
   *   Produces the temporary file and returns its path.
   */
  private function download(callable $generate, string $extension, string $contentType): Response {
    try {
      $path = $generate();
    }
    catch (ExportException $e) {
      $this->getLogger('egam_export')->error('Export failed: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('The export failed. See the "egam_export" log channel for details.'));
      return $this->redirect('egam_export.page');
    }

    $filename = 'egam-export-' . gmdate('Y-m-d', $this->time->getRequestTime()) . '.' . $extension;
    $response = new BinaryFileResponse($path, 200, ['Content-Type' => $contentType], FALSE);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->headers->addCacheControlDirective('no-store');
    $response->deleteFileAfterSend(TRUE);
    return $response;
  }

}
