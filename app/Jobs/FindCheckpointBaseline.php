<?php

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Checkpoint;
use Illuminate\Support\Facades\Log;

class FindCheckpointBaseline extends Job
{
    /**
     * @var Checkpoint
     */
    public $checkpoint;

    public function __construct(Checkpoint $checkpoint)
    {
        $this->checkpoint = $checkpoint;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $checkpoint = $this->checkpoint;
        // Ensure that checkpoint is up to date.
        $checkpoint->refresh();

        // Bail out if checkpoint disappeared.
        if (!$checkpoint->exists) {
            return;
        }

        Log::info(sprintf(
            'Finding Checkpoint baselines for %s (snapshot %s)',
            $checkpoint->id,
            $checkpoint->snapshot->id
        ));
        $baseline_url = '';
        $baseline = $checkpoint->snapshot->getBaseline();
        // Bail out if baseline has disappeared in the meantime.
        while ($baseline) {
            $baseCheckpoint = $baseline->checkpoints()->where([
                'name' => $checkpoint->name,
                'meta' => $checkpoint->meta
            ])->first();
            // No parent baseline, break out.
            if (!$baseCheckpoint) {
                break;
            }

            // If the baseline is approved use it's image as our baseline
            // image. This even works for deleted images, as they have an
            // empty string for URL, and new images gets an empty baseline
            // URL.
            if ($baseCheckpoint->status == Checkpoint::STATUS_APPROVED) {
                $baseline_url = $baseCheckpoint->image_url;
                break;
            }
            $baseline = $baseline->getBaseline();
        }

        $checkpoint->baseline_url = $baseline_url;
        $checkpoint->save();
    }
}
