<?php

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Clients\Contracts\Keeper;
use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageController extends BaseController
{
    /**
     * @var Keeper
     */
    protected $keeper;

    public function __construct(Keeper $keeper)
    {
        $this->keeper = $keeper;
    }

    public function get($sha)
    {
        $image = $this->keeper->get($sha);
        if (!$image) {
            throw new NotFoundHttpException();
        }

        $response = new Response($image);
        $response->headers->set('Content-Type', 'image/png');
        return $response;
    }
}
