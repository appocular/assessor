<?php

namespace Appocular\Assessor\Jobs;

use Appocular\Clients\Contracts\Differ;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitDiff extends Job
{
    public $image_url;
    public $baseline_url;

    public function __construct(string $image_url, string $baseline_url)
    {
        $this->image_url = $image_url;
        $this->baseline_url = $baseline_url;
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
            $this->image_url,
            $this->baseline_url
        ));
        try {
            $differ->submit($this->image_url, $this->baseline_url);
        } catch (Throwable $e) {
            Log::error(sprintf(
                'Error submitting diff image %s, baseline %s: %s',
                $this->image_url,
                $this->baseline_url,
                $e->getMessage()
            ));
        }
    }
}
