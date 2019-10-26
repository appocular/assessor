<?php

namespace Appocular\Assessor;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Jobs\FindCheckpointBaseline;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Snapshot extends Model
{
    /**
     * Snapshot status is pending.
     */
    const STATUS_UNKNOWN = 'unknown';

    /**
     * Snapshot passed. All checkpoints either passed or is approved or ignored.
     */
    const STATUS_PASSED = 'passed';

    /**
     * Snapshot failed. Rejected checkpoints exists.
     */
    const STATUS_FAILED = 'failed';

    /**
     * Snapshot is still pending (batch still running).
     */
    const RUN_STATUS_PENDING = 'pending';

    /**
     * Snapshot is waiting for user action (unknown checkpoints exists).
     */
    const RUN_STATUS_WAITING = 'waiting';

    /**
     * Snapshot is done (all checkpoints have non-unknown status and no active batches).
     */
    const RUN_STATUS_DONE = 'done';

    protected $fillable = ['id', 'repo_id'];

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Get the repo for the snapshot.
     */
    public function repo()
    {
        return $this->belongsTo('Appocular\Assessor\Repo', 'repo_id', 'uri');
    }

    /**
     * Get the current batches for the snapshot.
     */
    public function batches()
    {
        return $this->hasMany('Appocular\Assessor\Batch');
    }

    /**
     * Get the checkpoints for the snapshot.
     */
    public function checkpoints()
    {
        return $this->hasMany('Appocular\Assessor\Checkpoint')->orderBy('name');
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
     * Is snapshot done?
     */
    public function isDone() : bool
    {
        return $this->run_status == self::RUN_STATUS_DONE;
    }

    /**
     * Trigger finding baselines of this snapshots checkpoints.
     *
     * Queues FindCheckpointBaseline jobs to find the baseline of the
     * individual checkpoints.
     */
    public function triggerCheckpointBaselining() : void
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
                        'id' => hash('sha256', $this->id . $baseCheckpoint->name),
                        'snapshot_id' => $this->id,
                        'name' => $baseCheckpoint->name,
                        'image_url' => '',
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
    public function updateStatus() : void
    {
        $this->refresh();
        $unknownCount = $this->checkpoints->where('status', Checkpoint::STATUS_UNKNOWN)->count();
        if ($this->checkpoints->where('status', Checkpoint::STATUS_REJECTED)->count() > 0) {
            $this->status = self::STATUS_FAILED;
        } elseif ($unknownCount > 0) {
            $this->status = self::STATUS_UNKNOWN;
        } else {
            $this->status = self::STATUS_PASSED;
        }

        if ($unknownCount > 0) {
            $this->run_status = self::RUN_STATUS_WAITING;
        } else {
            $this->run_status = $this->batches()->count() > 0 ? self::RUN_STATUS_PENDING : self::RUN_STATUS_DONE;
        }

        $this->save();
    }

    /**
     * Get descendant snapshots.
     */
    public function getDescendants() : Collection
    {
        return self::where(['baseline' => $this->id])->get();
    }
}
