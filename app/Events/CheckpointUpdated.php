<?php

namespace Appocular\Assessor\Events;

use Appocular\Assessor\Checkpoint;
use Illuminate\Queue\SerializesModels;

class CheckpointUpdated extends Event
{
    use SerializesModels;

    public $checkpoint;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Checkpoint $checkpoint)
    {
        $this->checkpoint = $checkpoint;
    }
}
