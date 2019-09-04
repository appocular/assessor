<?php

namespace Appocular\Assessor\Http\Resources;

use Appocular\Assessor\Http\Resources\CheckpointResource;
use Illuminate\Http\Resources\Json\Resource;

class SnapshotResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     *
     * @return array
     */
    public function toArray($request)
    {
        return [
            'self' => route('snapshot.show', ['id' => $this->id]),
            'status' => $this->status,
            'run_status' => $this->run_status,
            'checkpoints' => CheckpointResource::collection($this->checkpoints),
        ];
    }
}