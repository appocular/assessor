<?php

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Checkpoint;
use Appocular\Clients\Contracts\Keeper;
use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CheckpointController extends BaseController
{
    /**
     * @var Keeper
     */
    protected $imageStore;

    public function __construct(Keeper $keeper)
    {
        $this->keeper = $keeper;
    }

    public function get($id)
    {
        $checkpoint = Checkpoint::findOrFail($id);
        return new Response($checkpoint->toArray());
    }

    public function image($id)
    {
        $checkpoint = Checkpoint::findOrFail($id);
        $image = $this->keeper->get($checkpoint->image_url);
        if (!$image) {
            throw new NotFoundHttpException();
        }

        $response = new Response($image);
        $response->headers->set('Content-Type', 'image/png');
        return $response;
    }
}
