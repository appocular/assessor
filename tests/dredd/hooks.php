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
    // Check that the headers matches the documentation.
    if (!empty($transaction->expected->headers)) {
        foreach ($transaction->expected->headers as $name => $content) {
            if ($transaction->real->headers->{strtolower($name)} != $content) {
                $transaction->fail = "Difference in $name header payload.";
            }
        }
    }
    // Check that the payload matches the documentation.
    if (!empty($transaction->expected->body)) {
        $contentType = isset($transaction->expected->headers->{"Content-Type"}) ?
            $transaction->expected->headers->{"Content-Type"} : "";
        switch ($contentType) {
            case 'application/json':
                print("json");
                $actual = normalize_json($transaction->real->body);
                $expected = normalize_json($transaction->expected->body);
                break;

            default:
                print("other");
                // Simple comparison for everything else. This includes
                // text/plain which dredd apparently checks itself, but what
                // the hell, we'll check it too.
                $actual = $transaction->real->body;
                $expected = $transaction->expected->body;
        }

        // Replace documentation batch id with the current one.
        $replacements = array('58444f87-0525-429d-ba3c-d7b06cab748a' => $stash['batch_id']);
        $expected = strtr($expected, $replacements);

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

function normalize_json($json)
{
    if (!empty($json)) {
        return json_encode(array_sort_recursive(json_decode($json, true)));
    }
    return "";
}
