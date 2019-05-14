<?php

namespace Appocular\Assessor\Listeners;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Events\DiffSubmitted;

class UpdateCheckpointsDiffs
{
    /**
     * Handle the event.
     *
     * @param  SnapshotUpdated  $event
     * @return void
     */
    public function handle(DiffSubmitted $event)
    {
        Checkpoint::updateDiffs($event->image_kid, $event->baseline_kid, $event->diff_kid, $event->different);
    }
}
