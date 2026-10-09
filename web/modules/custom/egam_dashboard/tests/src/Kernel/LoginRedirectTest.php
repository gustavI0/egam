<?php

namespace Drupal\Tests\egam_dashboard\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\dashboard\DashboardRedirectHandler;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Logging in sends users who may see the dashboard to it.
 */
#[RunTestsInSeparateProcesses]
class LoginRedirectTest extends DashboardKernelTestBase {

  use UserCreationTrait;

  protected function setUp(): void {
    parent::setUp();
    $source = new FileStorage($this->root . '/../config/sync');
    $this->container->get('entity_type.manager')->getStorage('dashboard')
      ->createFromStorageRecord($source->read('dashboard.dashboard.egam'))
      ->save();
    // Uid 1 bypasses every permission check: spend it on a throwaway user.
    User::create(['name' => 'uid one', 'status' => 1])->save();
  }

  private function destinationAfterLogin(array $permissions, ?string $destination = NULL): ?string {
    $user = $this->createUser($permissions);
    $this->setCurrentUser($user);
    $request = Request::create('/user/login', 'POST', $destination ? ['destination' => $destination] : []);
    if ($destination) {
      $request->query->set('destination', $destination);
    }
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $this->container->get(DashboardRedirectHandler::class)->userLogin($user);

    return $request->query->get('destination');
  }

  public function testUserWhoMaySeeTheDashboardIsSentToIt(): void {
    $this->assertSame('/admin/dashboard', $this->destinationAfterLogin(['view egam dashboard']));
  }

  public function testUserWithoutThePermissionIsLeftAlone(): void {
    $this->assertNull($this->destinationAfterLogin(['access administration pages']));
  }

  public function testAnExplicitDestinationIsKept(): void {
    $this->assertSame('/admin/content', $this->destinationAfterLogin(['view egam dashboard'], '/admin/content'));
  }

}
