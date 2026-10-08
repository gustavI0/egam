<?php

namespace Drupal\Tests\egam_export\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Only users with the dedicated permission can reach the export.
 */
#[RunTestsInSeparateProcesses]
class ExportAccessTest extends BrowserTestBase {

  protected static $modules = [
    'text',
    'egam_global', 'egam_artwork', 'egam_artist', 'egam_game',
    'egam_museum', 'egam_screenshot', 'egam_export',
  ];

  protected $defaultTheme = 'stark';

  private const PATHS = [
    '/admin/config/egam/export',
    '/admin/config/egam/export/csv',
    '/admin/config/egam/export/xlsx',
  ];

  public function testAnonymousUserIsDenied(): void {
    foreach (self::PATHS as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  public function testSiteConfigurationAdminWithoutThePermissionIsDenied(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer site configuration']));
    foreach (self::PATHS as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  public function testPageShowsTokenProtectedLinks(): void {
    $this->drupalLogin($this->drupalCreateUser(['export egam data']));
    $this->drupalGet('/admin/config/egam/export');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Download CSV (zip)');
    $this->assertSession()->linkExists('Download XLSX');
    $this->assertSession()->linkByHrefExists('/admin/config/egam/export/csv?token=');
  }

  public function testDownloadWithoutTokenIsDenied(): void {
    $this->drupalLogin($this->drupalCreateUser(['export egam data']));
    $this->drupalGet('/admin/config/egam/export/csv');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('/admin/config/egam/export/xlsx');
    $this->assertSession()->statusCodeEquals(403);
  }

  public function testCsvDownloadIsAZipOfFiveFiles(): void {
    $this->drupalLogin($this->drupalCreateUser(['export egam data']));
    $this->drupalGet('/admin/config/egam/export');
    $this->clickLink('Download CSV (zip)');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'application/zip');
    $this->assertSession()->responseHeaderContains('Content-Disposition', 'egam-export-');

    $path = tempnam(sys_get_temp_dir(), 'egam-test-');
    file_put_contents($path, $this->getSession()->getDriver()->getContent());
    $zip = new \ZipArchive();
    $this->assertTrue($zip->open($path));
    $this->assertSame(5, $zip->numFiles);
    $zip->close();
    unlink($path);
  }

  public function testXlsxDownloadIsAWorkbook(): void {
    $this->drupalLogin($this->drupalCreateUser(['export egam data']));
    $this->drupalGet('/admin/config/egam/export');
    $this->clickLink('Download XLSX');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    // An XLSX file is a zip archive.
    $this->assertStringStartsWith('PK', $this->getSession()->getDriver()->getContent());
  }

}
