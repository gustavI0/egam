<?php

namespace Drupal\Tests\egam_dashboard\Kernel;

use Drupal\egam_global\Entities;
use Drupal\user\Entity\User;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * What the four dashboard blocks show, and to whom.
 */
#[RunTestsInSeparateProcesses]
class DashboardBlocksTest extends DashboardKernelTestBase {

  use UserCreationTrait;

  private function userWith(array $permissions): User {
    // Uid 1 bypasses every permission check: spend it on a throwaway user.
    User::create(['name' => 'uid one', 'status' => 1])->save();
    $user = $this->createUser($permissions);
    $this->setCurrentUser($user);
    return $user;
  }

  private function block(string $id) {
    return $this->container->get('plugin.manager.block')->createInstance($id);
  }

  private function html(string $id): string {
    $build = $this->block($id)->build();
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  public function testExportLinkIsTheFirstShortcut(): void {
    $this->userWith(['access administration pages', 'export egam data', 'create artwork']);

    $html = $this->html('egam_dashboard_shortcuts');

    $export = strpos($html, '/admin/config/egam/export');
    $this->assertNotFalse($export, 'The export link is missing');
    $this->assertLessThan(strpos($html, '/artwork/add'), $export, 'The export link must come first');
  }

  public function testExportLinkIsHiddenWithoutThePermission(): void {
    $this->userWith(['access administration pages', 'create artwork']);

    $html = $this->html('egam_dashboard_shortcuts');

    $this->assertStringNotContainsString('/admin/config/egam/export', $html);
    $this->assertStringContainsString('/artwork/add', $html);
  }

  public function testAddLinksOnlyForEntitiesTheUserMayCreate(): void {
    $this->userWith(['access administration pages', 'create artwork']);

    $html = $this->html('egam_dashboard_shortcuts');

    $this->assertStringContainsString('/artwork/add', $html);
    $this->assertStringNotContainsString('/museum/add', $html);
  }

  public function testCatalogueShowsEveryEntityWithItsFigures(): void {
    $this->make(Entities::Artwork, ['status' => 1]);
    $this->make(Entities::Artwork, ['status' => 0]);
    $this->userWith(['access administration pages', 'administer artwork']);

    $html = $this->html('egam_dashboard_catalogue');

    foreach (['Artworks', 'Artists', 'Games', 'Musea', 'Screenshots'] as $label) {
      $this->assertStringContainsString($label, $html);
    }
    $this->assertMatchesRegularExpression('#Artworks.*?<td[^>]*>\s*1\s*</td>\s*<td[^>]*>\s*1\s*</td>\s*<td[^>]*>\s*2\s*</td>#s', $html);
  }

  public function testGapsListTheNamesTheUserMayEdit(): void {
    $artwork = $this->make(Entities::Artwork, ['label' => 'Secret draft']);
    $this->userWith(['access administration pages', 'edit artwork']);

    $html = $this->html('egam_dashboard_gaps');

    $this->assertStringContainsString('Artworks without an artist', $html);
    $this->assertStringContainsString('Secret draft', $html);
    $this->assertStringContainsString('/artwork/' . $artwork->id() . '/edit', $html);
  }

  public function testGapsKeepNamesHiddenFromUsersWhoCannotEdit(): void {
    $this->make(Entities::Artwork, ['label' => 'Secret draft']);
    $this->userWith(['access administration pages']);

    $html = $this->html('egam_dashboard_gaps');

    $this->assertStringContainsString('Artworks without an artist', $html);
    $this->assertStringNotContainsString('Secret draft', $html);
  }

  public function testGapsSayWhenNothingIsMissing(): void {
    $this->userWith(['access administration pages']);

    $html = $this->html('egam_dashboard_gaps');

    $this->assertStringContainsString('Nothing is missing', $html);
  }

  public function testRecentChangesLinkToTheEditForm(): void {
    $museum = $this->make(Entities::Museum, ['label' => 'Louvre']);
    $this->userWith(['access administration pages', 'edit museum']);

    $html = $this->html('egam_dashboard_recent');

    $this->assertStringContainsString('Louvre', $html);
    $this->assertStringContainsString('/museum/' . $museum->id() . '/edit', $html);
  }

  public function testRecentChangesHideEntitiesTheUserCannotEdit(): void {
    $this->make(Entities::Museum, ['label' => 'Louvre']);
    $this->userWith(['access administration pages']);

    $this->assertStringNotContainsString('Louvre', $this->html('egam_dashboard_recent'));
  }

  #[DataProvider('blockIds')]
  public function testBlocksAreDeniedWithoutAdministrationAccess(string $id): void {
    $user = $this->userWith(['view artwork']);

    $this->assertFalse($this->block($id)->access($user), $id);
  }

  #[DataProvider('blockIds')]
  public function testBlocksAreAllowedWithAdministrationAccess(string $id): void {
    $user = $this->userWith(['access administration pages']);

    $this->assertTrue($this->block($id)->access($user), $id);
  }

  public static function blockIds(): array {
    return array_map(fn ($id) => [$id], [
      'egam_dashboard_shortcuts',
      'egam_dashboard_catalogue',
      'egam_dashboard_gaps',
      'egam_dashboard_recent',
    ]);
  }

}
