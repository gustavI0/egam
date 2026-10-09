<?php

namespace Drupal\Tests\egam_dashboard\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\dashboard\Entity\Dashboard;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The exported dashboard in config/sync is valid and laid out as agreed.
 */
#[RunTestsInSeparateProcesses]
class DashboardConfigTest extends DashboardKernelTestBase {

  private function importDashboard(): Dashboard {
    $source = new FileStorage($this->root . '/../config/sync');
    $record = $source->read('dashboard.dashboard.egam');
    $this->assertIsArray($record, 'Missing dashboard.dashboard.egam in config/sync');
    $dashboard = $this->container->get('entity_type.manager')->getStorage('dashboard')->createFromStorageRecord($record);
    $dashboard->save();
    return $dashboard;
  }

  /**
   * Block plugin ids of the dashboard, in display order.
   */
  private function blockIds(Dashboard $dashboard): array {
    $ids = [];
    foreach ($dashboard->getSections() as $section) {
      $components = $section->getComponents();
      uasort($components, static fn ($a, $b): int => $a->getWeight() <=> $b->getWeight());
      foreach ($components as $component) {
        $ids[] = $component->getPluginId();
      }
    }
    return $ids;
  }

  public function testEveryBlockOfTheDashboardExists(): void {
    $definitions = $this->container->get('plugin.manager.block')->getDefinitions();

    foreach ($this->blockIds($this->importDashboard()) as $id) {
      $this->assertArrayHasKey($id, $definitions, "Unknown block $id");
    }
  }

  public function testExportShortcutsComeFirst(): void {
    $ids = $this->blockIds($this->importDashboard());

    $this->assertSame('egam_dashboard_shortcuts', $ids[0]);
    $this->assertEqualsCanonicalizing([
      'egam_dashboard_shortcuts', 'egam_dashboard_catalogue', 'egam_dashboard_gaps',
      'egam_dashboard_recent', 'dashboard_site_status',
    ], $ids);
  }

  public function testDashboardIsEnabledSoLoginRedirectsToIt(): void {
    $this->assertTrue($this->importDashboard()->status());
  }

}
