<?php

$source = file_get_contents(dirname(__DIR__) . '/Squad.module.php');
if ($source === false) throw new RuntimeException('Could not read Squad.module.php.');

$checks = [
    'missing-provider branch uses normalized ask error response' => str_contains(
        $source,
        'return $this->askErrorResponse("No active provider found for \'{$providerKey}\'");'
    ),
    'provider-exception branch uses normalized ask error response' => str_contains(
        $source,
        'return $this->askErrorResponse($e->getMessage());'
    ),
    'ask error helper explicitly marks response uncached' => str_contains(
        $source,
        "\$result['cached'] = false;"
    ),
];

foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException('Squad ask contract check failed: ' . $label);
}

echo "Squad ask error contract checks passed.\n";
