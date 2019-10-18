<?php

namespace Appocular\Assessor\Observers;

use Appocular\Assessor\History;
use Appocular\Assessor\Jobs\SnapshotBaselining;

class HistoryObserver
{
    /**
     * Handle the History "saved" event.
     */
    public function saved(History $history)
    {
        dispatch(new SnapshotBaselining($history->snapshot));
    }
}
