<?php

$testsPath = $argv[1] ?? null;
$requiredSymbols = array_slice($argv, 2);

if (!is_string($testsPath) || $testsPath === '') {
  fwrite(STDERR, "Usage: php tests/bin/run-optional-suite.php <tests-path> [required-class ...]
");
  exit(1);
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$missing = [];

foreach ($requiredSymbols as $symbol) {
  if (!class_exists($symbol) && !interface_exists($symbol) && !trait_exists($symbol)) {
    $missing[] = $symbol;
  }
}

if ($missing !== []) {
  fwrite(STDOUT, 'Skipping optional integration tests because these packages are not installed: ' . implode(', ', $missing) . PHP_EOL);
  exit(0);
}

$command = escapeshellarg(PHP_BINARY)
  . ' -d register_argc_argv=On '
  . escapeshellarg(dirname(__DIR__, 2) . '/vendor/bin/pest')
  . ' '
  . escapeshellarg($testsPath);

passthru($command, $exitCode);
exit((int) $exitCode);
