<?php

namespace Appocular\Assessor;

use GuzzleHttp\Client;
use RuntimeException;

class Differ
{
    /**
     * HTTP client.
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Construct Differ client.
     *
     * @param Client $client
     *   HTTP client to use.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Submit diffing request to Differ.
     */
    public function submit($image_kid, $baseline_kid)
    {
        $response = $this->client->post('diff', ['json' => ['image_kid' => $image_kid, 'baseline_kid' => $baseline_kid]]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Bad response from Differ.');
        }
    }
}
