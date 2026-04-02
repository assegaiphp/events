<?php

namespace Assegai\Events\Assegai\Console;

use Assegai\Console\Core\Packages\PackageInstallContext;
use Assegai\Console\Core\Packages\PackageInstallerInterface;
use Assegai\Console\Core\Packages\RootModuleIntegrator;

class EventsPackageInstaller implements PackageInstallerInterface
{
  public function install(PackageInstallContext $context): int
  {
    return RootModuleIntegrator::importModule(
      $context->workspace,
      ['Assegai\\Events\\Assegai\\EventsModule'],
      ['EventsModule::class'],
      $context->output,
    );
  }
}
