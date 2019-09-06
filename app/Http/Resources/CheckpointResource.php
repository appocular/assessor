<?php

namespace Appocular\Assessor\Http\Resources;

use Illuminate\Http\Resources\Json\Resource;

class CheckpointResource extends Resource
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
            'self' => route('checkpoint.show', ['id' => $this->id]),
            'name' => $this->name,
            'image_url' => $this->image_url,
            'baseline_url' => $this->baseline_url,
            'diff_url' => $this->diff_url,
            'status' => $this->status,
            'diff_status' => $this->diff_status,
            'actions' => [
                'approve' => route('checkpoint.approve', ['id' => $this->id]),
                'reject' => route('checkpoint.reject', ['id' => $this->id]),
                'ignore' => route('checkpoint.ignore', ['id' => $this->id]),
            ],
        ];
    }
}
