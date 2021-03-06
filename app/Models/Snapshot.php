<?php

declare(strict_types=1);

namespace Appocular\Assessor\Models;

use Appocular\Assessor\Jobs\FindCheckpointBaseline;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Throwable;

class Snapshot extends Model
{
    /**
     * Snapshot status is pending.
     */
    public const STATUS_UNKNOWN = 'unknown';

    /**
     * Snapshot passed. All checkpoints either passed or is approved or ignored.
     */
    public const STATUS_PASSED = 'passed';

    /**
     * Snapshot failed. Rejected checkpoints exists.
     */
    public const STATUS_FAILED = 'failed';

    /**
     * Snapshot needs human input. Unknown checkpoints exists.
     */
    public const PROCESSING_STATUS_PENDING = 'pending';

    /**
     * Snapshot has been processed. No unknown checkpoints exists.
     */
    public const PROCESSING_STATUS_DONE = 'done';

    /**
     * Snapshot is still pending (batch still running/pending checkpoints).
     */
    public const RUN_STATUS_PENDING = 'pending';

    /**
     * Snapshot is done (all checkpoints have non-unknown status and no active batches).
     */
    public const RUN_STATUS_DONE = 'done';

    /**
     * Tell Eloquent which properties are fillable.
     *
     * @var array<string>
     */
    protected $fillable = ['id', 'repo_id'];

    /**
     * Tell Eloquent that our key isn't an incrementing number.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tell Eloquent that our key is a string.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Get the repo for the snapshot.
     */
    public function repo(): BelongsTo
    {
        return $this->belongsTo('Appocular\Assessor\Models\Repo', 'repo_id', 'uri');
    }

    /**
     * Get the current batches for the snapshot.
     */
    public function batches(): HasMany
    {
        return $this->hasMany('Appocular\Assessor\Models\Batch');
    }

    /**
     * Get the checkpoints for the snapshot.
     */
    public function checkpoints(): HasMany
    {
        return $this->hasMany('Appocular\Assessor\Models\Checkpoint')->orderBy('name');
    }

    /**
     * Get the history for the snapshot.
     */
    public function history(): HasOne
    {
        return $this->hasOne('Appocular\Assessor\Models\History');
    }

    /**
     * Whether the baseline has been identified.
     */
    public function baselineIdentified(): bool
    {
        return $this->baseline !== null && $this->baseline !== '';
    }

    /**
     * Set the baseline.
     */
    public function setBaseline(Snapshot $baseline): void
    {
        $this->baseline = $baseline->id;
    }

    /**
     * Set the baseline to none.
     */
    public function setNoBaseline(): void
    {
        $this->baseline = '';
    }

    /**
     * Get baseline.
     */
    public function getBaseline(): ?Snapshot
    {
        if (!$this->baselineIdentified()) {
            return null;
        }

        return self::find($this->baseline);
    }

    /**
     * Is snapshot done?
     */
    public function isDone(): bool
    {
        return $this->run_status === self::RUN_STATUS_DONE;
    }

    /**
     * Trigger finding baselines of this snapshots checkpoints.
     *
     * Queues FindCheckpointBaseline jobs to find the baseline of the
     * individual checkpoints.
     */
    public function triggerCheckpointBaselining(): void
    {
        $baseline = $this->getBaseline();

        if (!$baseline) {
            return;
        }

        Log::info(\sprintf('Collectiong checkpoints for baselines finding for snapshot %s', $this->id));
        $baselineCheckpoints = [];

        foreach ($baseline->checkpoints()->get() as $checkpoint) {
            if (!$checkpoint->shouldPropagate()) {
                continue;
            }

            $baselineCheckpoints[$checkpoint->identifier()] = $checkpoint;
        }

        foreach ($this->checkpoints()->get() as $checkpoint) {
            unset($baselineCheckpoints[$checkpoint->identifier()]);
        }

        // Create imageless checkpoints for the remaining checkpoints in
        // the baseline so they exist in this snapshot.
        foreach ($baselineCheckpoints as $baseCheckpoint) {
            try {
                $checkpoint = $baseCheckpoint->createExpected($this);
            } catch (Throwable $e) {
                // We'll assume that any errors is because someone beat us
                // in creating the checkpoint, and quietly chug along.
            }
        }

        // Now that both existing and expected checkpoints exists in the
        // database, queue baseline finding.
        foreach ($this->checkpoints()->get() as $checkpoint) {
            \dispatch(new FindCheckpointBaseline($checkpoint));
        }
    }

    /**
     * Update snapshot status.
     *
     * Updates the status depending on the status of it's checkpoints.
     */
    public function updateStatus(): void
    {
        $this->refresh();
        $hasRejected = $this->checkpoints->where('approval_status', Checkpoint::APPROVAL_STATUS_REJECTED)->count() > 0;
        $hasPending = $this->checkpoints->where('image_status', Checkpoint::IMAGE_STATUS_PENDING)->count() > 0;
        $hasUnknown = $this->checkpoints->where('approval_status', Checkpoint::APPROVAL_STATUS_UNKNOWN)->count() > 0;
        $hasUnknownDiffs = $this->checkpoints->where('diff_status', Checkpoint::DIFF_STATUS_UNKNOWN)
            ->where('image_url')->where('baseline_url')->count() > 0;
        $hasRunningBatches = $this->batches()->count() > 0;

        if ($hasRejected) {
            $this->status = self::STATUS_FAILED;
        } elseif ($hasUnknown || $hasUnknownDiffs || $hasPending || $hasRunningBatches) {
            $this->status = self::STATUS_UNKNOWN;
        } else {
            $this->status = self::STATUS_PASSED;
        }

        // Should be pending if there's missing diffs, expected images, etc.
        // but we're assuming those will have an unknown processing state too.
        $this->processing_status = $hasUnknown ?
            self::PROCESSING_STATUS_PENDING :
            self::PROCESSING_STATUS_DONE;


        $this->run_status = $hasPending || $hasUnknownDiffs || $hasRunningBatches ?
            self::RUN_STATUS_PENDING :
            self::RUN_STATUS_DONE;

        $this->save();
    }

    /**
     * Get descendant snapshots.
     */
    public function getDescendants(): Collection
    {
        return self::where(['baseline' => $this->id])->get();
    }
}
