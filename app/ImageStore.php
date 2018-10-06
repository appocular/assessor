<?php

namespace Ogle\Assessor;

class ImageStore
{
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
