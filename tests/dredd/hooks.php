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
    // Dry run support.
    if (!isset($transaction->real)) {
        return;
    }
    $replacements = array('58444f87-0525-429d-ba3c-d7b06cab748a' => $stash['batch_id']);

    // Check that the headers matches the documentation.
    if (!empty($transaction->expected->headers)) {
        foreach ($transaction->expected->headers as $name => $content) {
            // Replace documentation batch id with the current one.
            $content = strtr($content, $replacements);
            if (!isset($transaction->real->headers->{strtolower($name)})) {
                $transaction->fail = "No $name header in reply.";
            }
            elseif ($transaction->real->headers->{strtolower($name)} != $content) {
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
                $actual = normalize_json($transaction->real->body);
                $expected = normalize_json($transaction->expected->body);
                break;

            default:
                // Simple comparison for everything else. This includes
                // text/plain which dredd apparently checks itself, but what
                // the hell, we'll check it too.
                $actual = $transaction->real->body;
                $expected = $transaction->expected->body;
        }

        // Replace documentation batch id with the current one.
        $expected = strtr($expected, $replacements);

        if ($actual != $expected) {
            $transaction->fail = "Difference in payload.";
        }
    }
});

Hooks::after('Batch resource > Create batch > Example 1', function (&$transaction) use (&$stash) {
    // Dry run support.
    if (!isset($transaction->real)) {
        return;
    }
    // Save created batch ID for use in subsequent tests.
    if (isset($transaction->real->headers->location)) {
        $parts = explode('/', $transaction->real->headers->location);
        $id = array_pop($parts);
        print_r($id);
        $stash['batch_id'] = $id;
    }
    // $json_data = json_decode($transaction->real->body);
});

function normalize_json($json)
{
    if (!empty($json)) {
        $data = json_decode($json, true);
        if (is_array($data)) {
            return json_encode(array_sort_recursive($data));
        }
    }
    return "";
}
