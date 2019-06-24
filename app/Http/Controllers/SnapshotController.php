<?php

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Http\Resources\SnapshotResource;
use Appocular\Assessor\Snapshot;
use Illuminate\Http\Response;
use Laravel\Lumen\Routing\Controller as BaseController;

class SnapshotController extends BaseController
{
    public function show($id)
    {
        $snapshot = Snapshot::with('checkpoints')->findOrFail($id);

        return new SnapshotResource($snapshot);
    }
}
