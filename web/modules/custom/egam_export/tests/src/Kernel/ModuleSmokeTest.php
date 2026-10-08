<?php

namespace Drupal\Tests\egam_export\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The module installs next to the five entity modules and defines its permission.
 */
#[RunTestsInSeparateProcesses]
class ModuleSmokeTest extends ExportKernelTestBase {

  public function testPermissionIsDefined(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();
    $this->assertArrayHasKey('export egam data', $permissions);
  }

  public function testPermissionIsRestricted(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();
    $this->assertTrue($permissions['export egam data']['restrict access']);
  }

}
