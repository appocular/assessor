<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Http\Resources\SnapshotResource;
use Appocular\Assessor\Snapshot;
use Laravel\Lumen\Routing\Controller as BaseController;

class SnapshotController extends BaseController
{
    public function show(string $id): SnapshotResource
    {
        $snapshot = Snapshot::with('checkpoints')->findOrFail($id);

        return new SnapshotResource($snapshot);
    }
}
