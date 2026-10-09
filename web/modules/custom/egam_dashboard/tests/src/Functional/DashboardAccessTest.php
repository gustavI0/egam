<?php

namespace Drupal\Tests\egam_dashboard\Functional;

use Drupal\Core\Config\FileStorage;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Who reaches the dashboard, and where the login leads.
 */
#[RunTestsInSeparateProcesses]
class DashboardAccessTest extends BrowserTestBase {

  protected static $modules = ['egam_dashboard'];

  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();
    $source = new FileStorage($this->root . '/../config/sync');
    $this->container->get('entity_type.manager')->getStorage('dashboard')
      ->createFromStorageRecord($source->read('dashboard.dashboard.egam'))
      ->save();
  }

  public function testAnonymousUserIsDenied(): void {
    $this->drupalGet('/admin/dashboard');

    $this->assertSession()->statusCodeEquals(403);
  }

  public function testUserWithoutTheDashboardPermissionIsDenied(): void {
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));

    $this->drupalGet('/admin/dashboard');

    $this->assertSession()->statusCodeEquals(403);
  }

  public function testLoginLandsOnTheDashboard(): void {
    $user = $this->drupalCreateUser(['view egam dashboard', 'access administration pages', 'export egam data']);

    // drupalLogin() goes through a one-time link, which the dashboard module
    // deliberately does not redirect: submit the real login form.
    $this->drupalGet('/user/login');
    $this->submitForm(['name' => $user->getAccountName(), 'pass' => $user->passRaw], 'Log in');

    $this->assertSame('/admin/dashboard', parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkByHrefExists('/admin/config/egam/export');
  }

  public function testLoginDoesNotRedirectUsersWithoutTheDashboardPermission(): void {
    $user = $this->drupalCreateUser(['access administration pages']);

    $this->drupalGet('/user/login');
    $this->submitForm(['name' => $user->getAccountName(), 'pass' => $user->passRaw], 'Log in');

    $this->assertNotSame('/admin/dashboard', parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));
  }

}
