<?php

namespace Drupal\Tests\egam_export\Kernel;

use Drupal\egam_export\Controller\ExportController;
use Drupal\egam_export\Export\CsvExporter;
use Drupal\egam_export\Export\ExportException;
use Drupal\egam_export\Export\ExportTableBuilder;
use Drupal\egam_export\Export\XlsxExporter;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[RunTestsInSeparateProcesses]
class ExportControllerTest extends ExportKernelTestBase {

  private function controller(?CsvExporter $csv = NULL, ?XlsxExporter $xlsx = NULL): ExportController {
    return new ExportController(
      $this->container->get(ExportTableBuilder::class),
      $csv ?? $this->container->get(CsvExporter::class),
      $xlsx ?? $this->container->get(XlsxExporter::class),
      $this->container->get('datetime.time'),
    );
  }

  public function testCsvDownloadIsAnAttachedZip(): void {
    $response = $this->controller()->downloadCsv();

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame('application/zip', $response->headers->get('Content-Type'));
    $this->assertMatchesRegularExpression(
      '/attachment; filename=egam-export-\d{4}-\d{2}-\d{2}\.zip/',
      $response->headers->get('Content-Disposition'),
    );
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $this->assertFileExists($response->getFile()->getPathname());
    @unlink($response->getFile()->getPathname());
  }

  public function testXlsxDownloadIsAnAttachedWorkbook(): void {
    $response = $this->controller()->downloadXlsx();

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
    $this->assertMatchesRegularExpression(
      '/attachment; filename=egam-export-\d{4}-\d{2}-\d{2}\.xlsx/',
      $response->headers->get('Content-Disposition'),
    );
    @unlink($response->getFile()->getPathname());
  }

  public function testFailedExportRedirectsWithAnErrorMessage(): void {
    $csv = $this->createMock(CsvExporter::class);
    $csv->method('export')->willThrowException(new ExportException('boom'));

    $response = $this->controller($csv)->downloadCsv();

    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertStringEndsWith('/admin/config/egam/export', $response->getTargetUrl());
    $this->assertCount(1, $this->container->get('messenger')->messagesByType('error'));
  }

  public function testPageOffersBothDownloads(): void {
    $page = $this->controller()->page();
    $this->assertArrayHasKey('csv', $page);
    $this->assertArrayHasKey('xlsx', $page);
  }

}
