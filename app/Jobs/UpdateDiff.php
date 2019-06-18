<?php

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Checkpoint;

class UpdateDiff extends Job
{
    public $image_url;
    public $baseline_url;
    public $diff_url;
    public $different;

    /**
     * @var Snapshot
     */
    public $snapshot;

    public function __construct(string $image_url, string $baseline_url, string $diff_url, bool $different)
    {
        $this->image_url = $image_url;
        $this->baseline_url = $baseline_url;
        $this->diff_url = $diff_url;
        $this->different = $different;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Checkpoint::updateDiffs($this->image_url, $this->baseline_url, $this->diff_url, $this->different);
    }
}
