<?php

use Dredd\Hooks;

$stash = [];

Hooks::beforeAll(function ($transaction) {
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE=/tmp/assessor-dredd.sqlite');
    exec(__DIR__ . '/../../artisan migrate:fresh');
});

Hooks::beforeEach(function ($transaction) use (&$stash) {
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

Hooks::after('Batch resource > Create batch > Example 1', function ($transaction) use (&$stash) {
    // Dry run support.
    if (!isset($transaction->real)) {
        return;
    }
    // Save created batch ID for use in subsequent tests.
    if (isset($transaction->real->headers->location)) {
        $parts = explode('/', $transaction->real->headers->location);
        $id = array_pop($parts);
        $stash['batch_id'] = $id;
    }
});
