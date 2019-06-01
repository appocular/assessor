<?php

namespace Appocular\Assessor\Jobs;

use Appocular\Clients\Contracts\Differ;
use Log;
use Throwable;

class SubmitDiff extends Job
{
    public $image_kid;
    public $baseline_kid;

    public function __construct(string $image_kid, string $baseline_kid)
    {
        $this->image_kid = $image_kid;
        $this->baseline_kid = $baseline_kid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Differ $differ)
    {
        Log::info(sprintf(
            'Submitting diff for image %s, baseline %s',
            $this->image_kid,
            $this->baseline_kid
        ));
        try {
            $differ->submit($this->image_kid, $this->baseline_kid);
        } catch (Throwable $e) {
            Log::error(sprintf(
                'Error submitting diff image %s, baseline %s: %s',
                $this->image_kid,
                $this->baseline_kid,
                $e->getMessage()
            ));
        }
    }
}
