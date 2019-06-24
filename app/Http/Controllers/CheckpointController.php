<?php

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Http\Resources\CheckpointResource;
use Appocular\Clients\Contracts\Keeper;
use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CheckpointController extends BaseController
{
    public function show($id)
    {
        $checkpoint = Checkpoint::findOrFail($id);
        return new CheckpointResource($checkpoint);
    }
}
