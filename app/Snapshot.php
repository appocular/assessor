<?php

namespace Appocular\Assessor;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\FindCheckpointBaseline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Snapshot extends Model
{
    /**
     * Snapshot status is pending.
     */
    const STATUS_UNKNOWN = 'unknown';

    /**
     * Snapshot passed. All checkpoints either passed or is approved.
     */
    const STATUS_PASSED = 'passed';

    /**
     * Snapshot failed. Unapproved failed checkpoints exists.
     */
    const STATUS_FAILED = 'failed';

    protected $fillable = ['id'];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $visible = ['id', 'checkpoints'];

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

    /**
     * Trigger finding baselines of this snapshots checkpoints.
     *
     * Queues FindCheckpointBaseline jobs to find the baseline of the
     * individual checkpoints.
     */
    public function triggerCheckpointBaselining()
    {
        if ($baseline = $this->getBaseline()) {
            Log::info(sprintf('Collectiong Checkpoints for baselines finding for snapshot %s', $this->id));
            $baselineCheckpoints = [];
            foreach ($baseline->checkpoints()->get() as $checkpoint) {
                if ($checkpoint->shouldPropagate()) {
                    $baselineCheckpoints[$checkpoint->name] = $checkpoint;
                }
            }

            foreach ($this->checkpoints()->get() as $checkpoint) {
                unset($baselineCheckpoints[$checkpoint->name]);
                dispatch(new FindCheckpointBaseline($checkpoint));
            }

            // Create imageless checkpoints for the remaining checkpoints in
            // the baseline so they exist in this snapshot.
            foreach ($baselineCheckpoints as $baseCheckpoint) {
                try {
                    $checkpoint = new Checkpoint([
                        'id' => hash('sha1', $this->id . $baseCheckpoint->name),
                        'snapshot_id' => $this->id,
                        'name' => $baseCheckpoint->name,
                        'image_sha' => '',
                    ]);
                    $checkpoint->save();
                    dispatch(new FindCheckpointBaseline($checkpoint));
                } catch (Throwable $e) {
                    // We'll assume that any errors is because someone beat us
                    // in creating the checkpoint, and quietly chug along.
                }
            }
        }
    }

    /**
     * Update snapshot status.
     *
     * Updates the status depending on the status of it's checkpoints.
     */
    public function updateStatus()
    {
        $this->refresh();
        if ($this->checkpoints->where('status', Checkpoint::STATUS_REJECTED)->count() > 0) {
            $this->status = self::STATUS_FAILED;
        } elseif ($this->checkpoints->where('status', Checkpoint::STATUS_UNKNOWN)->count() > 0) {
            $this->status = self::STATUS_UNKNOWN;
        } else {
            $this->status = self::STATUS_PASSED;
        }

        $this->save();
    }
}
