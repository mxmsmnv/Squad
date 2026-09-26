<?php

$source = file_get_contents(dirname(__DIR__) . '/SquadKeys.php');
if ($source === false) throw new RuntimeException('Could not read SquadKeys.php.');

$checks = [
    'no MySQL-only automatic update clause' => stripos($source, 'ON UPDATE CURRENT_TIMESTAMP') === false,
    'insert declares portable timestamp columns' => str_contains($source, '`created`,`modified`)'),
    'insert binds the creation timestamp' => str_contains($source, "':created' => \$now"),
    'insert binds the modification timestamp' => str_contains($source, "':modified' => \$now"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException('SquadKeys portability check failed: ' . $label);
}

echo "SquadKeys database portability checks passed.\n";
