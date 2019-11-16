<?php

declare(strict_types=1);

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Checkpoint;
use Illuminate\Support\Facades\Log;

class FindCheckpointBaseline extends Job
{
    /**
     * Checkpoint to baseline.
     *
     * @var \Appocular\Assessor\Checkpoint
     */
    public $checkpoint;

    public function __construct(Checkpoint $checkpoint)
    {
        $this->checkpoint = $checkpoint;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $checkpoint = $this->checkpoint;
        // Ensure that checkpoint is up to date.
        $checkpoint->refresh();

        // Bail out if checkpoint disappeared.
        if (!$checkpoint->exists) {
            return;
        }

        Log::info(\sprintf(
            'Finding Checkpoint baselines for %s (snapshot %s)',
            $checkpoint->id,
            $checkpoint->snapshot->id,
        ));
        $baseline_url = '';
        $baseline = $checkpoint->snapshot->getBaseline();

        // Bail out if baseline has disappeared in the meantime.
        while ($baseline) {
            $baseCheckpoint = $baseline->checkpoints()->where(static function ($query) use ($checkpoint): void {
                $query->where('name', $checkpoint->name);

                if (\is_null($checkpoint->meta)) {
                    $query->whereNull('meta');
                } else {
                    $query->where('meta', \json_encode($checkpoint->meta));
                }
            })->first();

            // No parent baseline, break out.
            if (!$baseCheckpoint) {
                break;
            }

            // If the baseline is approved use it's image as our baseline
            // image. This even works for deleted images, as they have an
            // empty string for URL, and new images gets an empty baseline
            // URL.
            if ($baseCheckpoint->approval_status === Checkpoint::APPROVAL_STATUS_APPROVED) {
                $baseline_url = $baseCheckpoint->image_url;

                break;
            }

            $baseline = $baseline->getBaseline();
        }

        $checkpoint->baseline_url = $baseline_url;
        $checkpoint->save();
    }
}
