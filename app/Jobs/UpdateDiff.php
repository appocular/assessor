<?php

namespace Appocular\Assessor\Jobs;

use Appocular\Assessor\Checkpoint;

class UpdateDiff extends Job
{
    public $image_kid;
    public $baseline_kid;
    public $diff_kid;
    public $different;

    /**
     * @var Snapshot
     */
    public $snapshot;

    public function __construct(string $image_kid, string $baseline_kid, string $diff_kid, bool $different)
    {
        $this->image_kid = $image_kid;
        $this->baseline_kid = $baseline_kid;
        $this->diff_kid = $diff_kid;
        $this->different = $different;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Checkpoint::updateDiffs($this->image_kid, $this->baseline_kid, $this->diff_kid, $this->different);
    }
}
