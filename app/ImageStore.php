<?php

namespace Ogle\Assessor;

use GuzzleHttp\Client;
use RuntimeException;

class ImageStore
{

    /**
     * HTTP client.
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Construct image store.
     *
     * @param Client $client
     *   HTTP client to use.
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Store image.
     *
     * @param string $data
     *   Binary PNG data.
     *
     * @return string
     *   SHA of stored image.
     */
    public function store(string $data) : string
    {
        $response = $this->client->post('image', ['body' => $data]);
        $reply = json_decode($response->getBody());
        if ($response->getStatusCode() !== 200 || !property_exists($reply, 'sha')) {
            throw new RuntimeException('Bad response from Keeper.');
        }
        return $reply->sha;
    }

    /**
     * Get image URL.
     *
     * Returns the URL of the image with the given SHA. Ensures that the SHA
     * corresponds to a stored image.
     *
     * @param string $sha
     *   SHA of image.
     *
     * @return string
     *   URL of image.
     */
    public function url(string $sha) : string
    {
    }
}
