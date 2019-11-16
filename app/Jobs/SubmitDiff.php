<?php

declare(strict_types=1);

namespace Appocular\Assessor\Jobs;

use Appocular\Clients\Contracts\Differ;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitDiff extends Job
{
    /**
     * URL of image to diff.
     *
     * @var string
     */
    public $image_url;

    /**
     * URL of baseline to diff.
     *
     * @var string
     */
    public $baseline_url;

    public function __construct(string $image_url, string $baseline_url)
    {
        $this->image_url = $image_url;
        $this->baseline_url = $baseline_url;
    }

    /**
     * Execute the job.
     */
    public function handle(Differ $differ): void
    {
        Log::info(\sprintf(
            'Submitting diff for image %s, baseline %s',
            $this->image_url,
            $this->baseline_url,
        ));

        try {
            $differ->submit($this->image_url, $this->baseline_url);
        } catch (Throwable $e) {
            Log::error(\sprintf(
                'Error submitting diff image %s, baseline %s: %s',
                $this->image_url,
                $this->baseline_url,
                $e->getMessage(),
            ));
        }
    }
}
