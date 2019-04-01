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

        // Bail out if checkpoint disappeared or the baseline is already set.
        if (!$checkpoint->exists || !is_null($checkpoint->baseline_sha)) {
            return;
        }

        Log::info(sprintf(
            'Finding Checkpoint baselines for %s (snapshot $s)',
            $checkpoint->id,
            $checkpoint->snapshot->id
        ));
        $baseline_sha = '';
        $baseline = $checkpoint->snapshot->getBaseline();
        // Bail out if baseline has disappeared in the meantime.
        while ($baseline) {
            $baseCheckpoint = $baseline->checkpoints()->where(['name' => $checkpoint->name])->first();
            // No parent baseline, break out.
            if (!$baseCheckpoint) {
                break;
            }

            // If the baseline is approved use it's image as our baseline
            // image. This even works for deleted images, as they have an
            // empty string for sha, and new images gets an empty baseline
            // sha.
            if ($baseCheckpoint->status == Checkpoint::STATUS_APPROVED) {
                $baseline_sha = $baseCheckpoint->image_sha;
                break;
            }
            $baseline = $baseline->getBaseline();
        }

        $checkpoint->baseline_sha = $baseline_sha;
        $checkpoint->save();
    }
}
