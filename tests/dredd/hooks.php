<?php

// phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified

declare(strict_types=1);

use Dredd\Hooks;

$stash = [];

// phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
Hooks::beforeAll(static function ($transaction): void {
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE=/tmp/assessor-dredd.sqlite');
    exec(__DIR__ . '/../../artisan migrate:fresh');
});

Hooks::beforeEach(static function ($transaction) use (&$stash): void {
    // Replace the batch ID in requests with the last one we created.
    if (isset($stash['batch_id'])) {
        $replacements = array('58444f87-0525-429d-ba3c-d7b06cab748a' => $stash['batch_id']);
        $transaction->request->uri = strtr($transaction->request->uri, $replacements);
        $transaction->fullPath = strtr($transaction->fullPath, $replacements);
    }

    // Replace PNG data placeholder with real base64 encoded PNG data.
    // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
    if (strpos($transaction->request->body, '<base64 PNG data>') !== false) {
        $pngData = file_get_contents(__DIR__ . '/../../fixtures/images/basn6a16.png');
        $replacements = array('<base64 PNG data>' => base64_encode($pngData));
        $transaction->request->body = strtr($transaction->request->body, $replacements);
    }
});

Hooks::after(
    'Snapshot submission > Batch resource > Create batch (entrypoint) > Example 1',
    static function ($transaction) use (&$stash): void {
        // Dry run support.
        if (!isset($transaction->real)) {
            return;
        }

        // Save created batch ID for use in subsequent tests.
        // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if (isset($transaction->real->headers->location)) {
            $parts = explode('/', $transaction->real->headers->location);
            $id = array_pop($parts);
            $stash['batch_id'] = $id;
        }
    },
);
