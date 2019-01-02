<?php

namespace Appocular\Assessor;

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
     * Get image.
     *
     * @param string $sha
     *   Image SHA to fetch.
     *
     * @return null|string
     *   PNG data or null if not found.
     */
    public function get($sha) : ?string
    {
        try {
            $response = $this->client->get('image/' . $sha);
            if ($response->getStatusCode() == 200) {
                return $response->getBody();
            }
        } catch (\Exception $e) {
            //
        }
        return null;
    }
}
