<?php

declare(strict_types=1);

namespace Appocular\Assessor\Http\Resources;

use Appocular\Assessor\SlugGenerator;
use Illuminate\Http\Resources\Json\Resource;

class CheckpointResource extends Resource
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
        return [
            'self' => \route('checkpoint.show', ['id' => $this->id]),
            'name' => $this->name,
            'image_url' => $this->image_url,
            'baseline_url' => $this->baseline_url,
            'diff_url' => $this->diff_url,
            'image_status' => $this->image_status,
            'diff_status' => $this->diff_status,
            'approval_status' => $this->approval_status,
            'actions' => [
                'approve' => \route('checkpoint.approve', ['id' => $this->id]),
                'reject' => \route('checkpoint.reject', ['id' => $this->id]),
                'ignore' => \route('checkpoint.ignore', ['id' => $this->id]),
            ],
            'slug' => SlugGenerator::toSlug($this->name, $this->meta),
            'meta' => $this->meta,
        ];
    }
}
