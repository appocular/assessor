<?php

namespace Appocular\Assessor\Events;

use Appocular\Assessor\Snapshot;
use Illuminate\Queue\SerializesModels;

class SnapshotCreated extends Event
{
    use SerializesModels;

    public $snapshot;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Snapshot $snapshot)
    {
        $this->snapshot = $snapshot;
    }
}
