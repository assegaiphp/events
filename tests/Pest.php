<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$coreAutoload = dirname(__DIR__, 2) . '/core/vendor/autoload.php';

if (is_file($coreAutoload)) {
  require_once $coreAutoload;
}

$repoRoot = dirname(__DIR__, 2);
$prefixes = [
  'Assegai\\Core\\' => $repoRoot . '/core/src/',
  'Assegai\\Common\\' => $repoRoot . '/common/src/',
  'Assegai\\Orm\\' => $repoRoot . '/orm/src/',
  'Assegai\\Rabbitmq\\' => $repoRoot . '/rabbitmq/src/',
  'Assegai\\Collections\\' => $repoRoot . '/collections/src/',
  'Assegai\\Util\\' => $repoRoot . '/util/src/',
  'Assegai\\Validation\\' => $repoRoot . '/validation/src/',
  'Assegai\\Forms\\' => $repoRoot . '/forms/src/',
];

spl_autoload_register(static function (string $class) use ($prefixes): void {
  foreach ($prefixes as $prefix => $baseDirectory) {
    if (!str_starts_with($class, $prefix)) {
      continue;
    }

    $relativeClass = substr($class, strlen($prefix));
    $filename = rtrim($baseDirectory, '/\\') . '/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($filename)) {
      require_once $filename;
    }

    return;
  }
}, prepend: true);
