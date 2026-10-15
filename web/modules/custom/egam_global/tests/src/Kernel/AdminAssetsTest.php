<?php

namespace Drupal\Tests\egam_global\Kernel;

use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\egam_global\Hook\AdminAssetsHooks;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Route;

/**
 * The admin stylesheet is attached to administration pages only.
 */
#[RunTestsInSeparateProcesses]
class AdminAssetsTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'egam_global'];

  private function attachmentsOn(Route $route): array {
    $request = Request::create('/somewhere');
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'test.route');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $attachments = [];
    $this->container->get(AdminAssetsHooks::class)->attachAdminStyles($attachments);
    return $attachments;
  }

  public function testAdministrationPagesGetTheAdminLibrary(): void {
    $attachments = $this->attachmentsOn(new Route('/admin/anything', [], [], ['_admin_route' => TRUE]));

    $this->assertContains('egam_global/admin', $attachments['#attached']['library']);
  }

  public function testPublicPagesDoNotGetTheAdminLibrary(): void {
    $attachments = $this->attachmentsOn(new Route('/oeuvres'));

    $this->assertArrayNotHasKey('#attached', $attachments);
  }

  public function testTheResultVariesWithTheRoute(): void {
    $attachments = $this->attachmentsOn(new Route('/oeuvres'));

    $this->assertContains('route', $attachments['#cache']['contexts']);
  }

  public function testTheLibraryExistsAndPointsToAnExistingStylesheet(): void {
    $library = $this->container->get('library.discovery')->getLibraryByName('egam_global', 'admin');

    $this->assertIsArray($library);
    $css = $library['css'][0]['data'];
    $this->assertFileExists($this->root . '/' . $css);
  }

}
