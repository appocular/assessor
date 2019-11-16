<?php

declare(strict_types=1);

namespace Appocular\Assessor\Observers;

use Appocular\Assessor\History;
use Appocular\Assessor\Jobs\SnapshotBaselining;

class HistoryObserver
{
    /**
     * Handle the History "saved" event.
     */
    public function saved(History $history): void
    {
        \dispatch(new SnapshotBaselining($history->snapshot));
    }
}
