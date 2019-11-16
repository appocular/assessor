<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Controllers;

use Appocular\Assessor\Http\Resources\CheckpointResource;
use Appocular\Assessor\Models\Checkpoint;
use Laravel\Lumen\Routing\Controller as BaseController;

class CheckpointController extends BaseController
{
    public function show(string $id): CheckpointResource
    {
        $checkpoint = Checkpoint::findOrFail($id);

        return new CheckpointResource($checkpoint);
    }

    public function approve(string $id): void
    {
        $checkpoint = Checkpoint::findOrFail($id);

        $checkpoint->approve();
    }

    public function reject(string $id): void
    {
        $checkpoint = Checkpoint::findOrFail($id);

        $checkpoint->reject();
    }

    public function ignore(string $id): void
    {
        $checkpoint = Checkpoint::findOrFail($id);

        $checkpoint->ignore();
    }
}
