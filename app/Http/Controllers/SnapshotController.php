<?php

namespace Appocular\Assessor\Http\Controllers;

use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;
use Appocular\Assessor\Snapshot;

class SnapshotController extends BaseController
{
    public function index($id)
    {
        $snapshot = Snapshot::with('checkpoints')->findOrFail($id);

        return (new Response($snapshot->toJson()));
    }
}
