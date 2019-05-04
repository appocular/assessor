<?php

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\ImageStore;
use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageController extends BaseController
{
    /**
     * @var ImageStore
     */
    protected $imageStore;

    public function __construct(ImageStore $imageStore)
    {
        $this->imageStore = $imageStore;
    }

    public function get($sha)
    {
        $image = $this->imageStore->get($sha);
        if (!$image) {
            throw new NotFoundHttpException();
        }

        $response = new Response($image);
        $response->headers->set('Content-Type', 'image/png');
        return $response;
    }
}
