<?php

namespace Assegai\Events\Bridge\Console;

use Assegai\Console\Core\Packages\PackageInstallContext;
use Assegai\Console\Core\Packages\PackageInstallerInterface;
use Assegai\Console\Core\Packages\RootModuleIntegrator;

class EventsPackageInstaller implements PackageInstallerInterface
{
  public function install(PackageInstallContext $context): int
  {
    return RootModuleIntegrator::importModule(
      $context->workspace,
      ['Assegai\\Events\\Bridge\\EventsModule'],
      ['EventsModule::class'],
      $context->output,
    );
  }
}
