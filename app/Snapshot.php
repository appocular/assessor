<?php

namespace Appocular\Assessor;

use Appocular\Assessor\Events\SnapshotCreated;
use Illuminate\Database\Eloquent\Model;

class Snapshot extends Model
{
    protected $fillable = ['id'];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $visible = ['id', 'checkpoints'];

    protected $dispatchesEvents = [
        'created' => SnapshotCreated::class,
    ];
    /**
     * Get the checkpoints for the snapshot.
     */
    public function checkpoints()
    {
        return $this->hasMany('Appocular\Assessor\Checkpoint');
    }

    /**
     * Get the history for the snapshot.
     */
    public function history()
    {
        return $this->hasOne('Appocular\Assessor\History');
    }

    /**
     * Whether the baseline has been identified.
     */
    public function baselineIdentified() : bool
    {
        return !empty($this->baseline);
    }

    /**
     * Set the baseline.
     */
    public function setBaseline(Snapshot $baseline) : void
    {
        $this->baseline = $baseline->id;
    }

    /**
     * Set the baseline to none.
     */
    public function setNoBaseline() : void
    {
        $this->baseline = '';
    }

    /**
     * Get baseline.
     */
    public function getBaseline() : ?Snapshot
    {
        if ($this->baselineIdentified()) {
            return self::find($this->baseline);
        }
        return null;
    }
}
