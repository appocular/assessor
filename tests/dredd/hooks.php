<?php

use Dredd\Hooks;

$stash = [];

Hooks::beforeAll(function (&$transaction) {
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE=/tmp/assessor-dredd.sqlite');
    exec(__DIR__ . '/../../artisan migrate:fresh');
});

Hooks::beforeEach(function (&$transaction) use (&$stash) {
    // Replace the batch ID in requests with the last one we created.
    if (isset($stash['batch_id'])) {
        $replacements = array('58444f87-0525-429d-ba3c-d7b06cab748a' => $stash['batch_id']);
        $transaction->request->uri = strtr($transaction->request->uri, $replacements);
        $transaction->fullPath = strtr($transaction->fullPath, $replacements);
    }

    // Replace PNG data placeholder with real base64 encoded PNG data.
    if (strpos($transaction->request->body, '<base64 PNG data>') !== false) {
        $pngData = file_get_contents(__DIR__ . '/../../fixtures/images/basn6a16.png');
        $replacements = array('<base64 PNG data>' => base64_encode($pngData));
        $transaction->request->body = strtr($transaction->request->body, $replacements);
    }
});

Hooks::afterEach(function (&$transaction) use (&$stash) {
    // Check that the JSON payload matches the documentation.
    if (!empty($transaction->expected->body)) {
        if (!empty($transaction->real->body)) {
            $actual = json_encode(array_sort_recursive(
                json_decode($transaction->real->body, true)
            ));
        } else {
            // No body, we'll compare with an empty result.
            $actual = json_encode([]);
        }
        $expected = array_sort_recursive(
            json_decode($transaction->expected->body, true)
        );

        // For the batch creation call, expect the ID that was just returned.
        if (isset($stash['batch_id']) &&
            $transaction->fullPath == '/batch' &&
            isset($expected['id']) &&
            $expected['id'] == '58444f87-0525-429d-ba3c-d7b06cab748a') {
            $expected['id'] = $stash['batch_id'];
        }
        $expected = json_encode($expected);

        if ($actual != $expected) {
            $transaction->fail = "Difference in JSON payload.";
        }
    }
});

Hooks::after('Batch resource > Create batch > Example 1', function (&$transaction) use (&$stash) {
    // Save created batch ID for use in subsequent tests.
    $json_data = json_decode($transaction->real->body);
    $stash['batch_id'] = $json_data->id;
});
