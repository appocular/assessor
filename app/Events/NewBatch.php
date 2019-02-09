<?php

namespace Appocular\Assessor\Events;

class NewBatch extends Event
{
    public $snapshotId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $snapshotId)
    {
        $this->snapshotId = $snapshotId;
    }
}
