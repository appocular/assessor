<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Resources;

use Illuminate\Http\Resources\Json\Resource;

class SnapshotResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string|array, string>
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
     */
    public function toArray($request): array
    {
        $baseline = $this->getBaseline();

        return [
            'self' => \route('snapshot.show', ['id' => $this->id]),
            'id' => $this->id,
            'status' => $this->status,
            'run_status' => $this->run_status,
            'processing_status' => $this->processing_status,
            'checkpoints' => CheckpointResource::collection($this->checkpoints),
            'baseline_url' => $baseline ? \route('snapshot.show', ['id' => $baseline->id]) : null,
        ];
    }
}
