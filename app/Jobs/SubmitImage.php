<?php

declare(strict_types=1);

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Models\Checkpoint;
use Appocular\Clients\Contracts\Keeper;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitImage extends Job
{
    /**
     * Checkpoint to submit image for.
     *
     * @var \Appocular\Assessor\Models\Checkpoint
     */
    public $checkpoint;

    /**
     * PNG image data, base64 encoded.
     *
     * @var string
     */
    public $pngData;

    public function __construct(Checkpoint $checkpoint, string $pngData)
    {
        $this->checkpoint = $checkpoint;
        $this->pngData = $pngData;
    }

    /**
     * Execute the job.
     */
    public function handle(Keeper $keeper): void
    {
        Log::info(\sprintf(
            'Submitting image for checkpoint %s',
            $this->checkpoint->id,
        ));

        try {
            $imageData = \base64_decode($this->pngData, true);

            $image_url = $keeper->store($imageData);
            $this->checkpoint->refresh();
            $this->checkpoint->image_url = $image_url;
            $this->checkpoint->save();
        } catch (Throwable $e) {
            Log::error(\sprintf(
                'Error submitting image for checkpoint %s: %s',
                $this->checkpoint->id,
                $e->getMessage(),
            ));

            // Rethrow so we'll retry later.
            throw $e;
        }
    }
}
