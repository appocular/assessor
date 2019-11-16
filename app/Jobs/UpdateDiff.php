<?php

declare(strict_types=1);

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Models\Checkpoint;

class UpdateDiff extends Job
{
    /**
     * URL of image.
     *
     * @var string
     */
    public $image_url;

    /**
     * URL of baseline.
     *
     * @var string
     */
    public $baseline_url;

    /**
     * URL of diff.
     *
     * @var string
     */
    public $diff_url;

    /**
     * Is a difference detected?
     *
     * @var bool
     */
    public $different;

    public function __construct(string $image_url, string $baseline_url, string $diff_url, bool $different)
    {
        $this->image_url = $image_url;
        $this->baseline_url = $baseline_url;
        $this->diff_url = $diff_url;
        $this->different = $different;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Checkpoint::updateDiffs($this->image_url, $this->baseline_url, $this->diff_url, $this->different);
    }
}
