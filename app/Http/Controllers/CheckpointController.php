<?php

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\ImageStore;
use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CheckpointController extends BaseController
{
    /**
     * @var ImageStore
     */
    protected $imageStore;

    public function __construct(ImageStore $imageStore)
    {
        $this->imageStore = $imageStore;
    }

    public function get($id)
    {
        $checkpoint = Checkpoint::findOrFail($id);
        return new Response($checkpoint->toArray());
    }

    public function image($id)
    {
        $checkpoint = Checkpoint::findOrFail($id);
        $image = $this->imageStore->get($checkpoint->image_sha);
        if (!$image) {
            throw new NotFoundHttpException();
        }

        $response = new Response($image);
        $response->headers->set('Content-Type', 'image/png');
        return $response;
    }
}
