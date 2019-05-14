<?php

namespace Appocular\Assessor\Events;

use Illuminate\Queue\SerializesModels;

class DiffSubmitted extends Event
{
    use SerializesModels;

    public $image_kid;
    public $baseline_kid;
    public $diff_kid;
    public $different;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $image_kid, string $baseline_kid, string $diff_kid, bool $different)
    {
        $this->image_kid = $image_kid;
        $this->baseline_kid = $baseline_kid;
        $this->diff_kid = $diff_kid;
        $this->different = $different;
    }
}
