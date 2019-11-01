<?php

namespace Appocular\Assessor;

use Appocular\Assessor\Snapshot;
use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_UNKNOWN = 'unknown';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_IGNORED = 'ignored';

    const DIFF_STATUS_UNKNOWN = 'unknown';
    const DIFF_STATUS_IDENTICAL = 'identical';
    const DIFF_STATUS_DIFFERENT = 'different';

    protected $fillable = ['id', 'name', 'snapshot_id', 'image_url', 'baseline_url', 'diff_url', 'meta'];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        // Store meta as JSON in the database.
        'meta' => 'array',
    ];


    /**
     * Get the snapshot for the checkpoint.
     */
    public function snapshot()
    {
        return $this->belongsTo('Appocular\Assessor\Snapshot');
    }

    /**
     * Reset Checkpoint baselines for snapshot.
     *
     * Deletes checkpoints without an image (they're placeholders for deleted
     * files), sets baseline for the rest to null and resets statuses.
     *
     * @param string $snapshotId
     *   Id of the snapshot to reset for.
     */
    public static function resetBaselines(string $snapshotId) : void
    {
        $checkpoint = new static();
        $checkpoint->newModelQuery()
            ->where([['snapshot_id', $snapshotId], ['image_url', null]])
            ->delete();
        $checkpoint->newModelQuery()
            ->where('snapshot_id', $snapshotId)
            ->update([
                'baseline_url' => null,
                'diff_url' => null,
                'status' => self::STATUS_UNKNOWN,
                'diff_status' => self::DIFF_STATUS_UNKNOWN,
            ]);
    }

    public static function cleanMeta(array $meta)
    {
        ksort($meta);
        return $meta;
    }

    public static function getId(string $snapshotId, $name, ?array $meta = null): string
    {
        return hash('sha256', $snapshotId . $name . ($meta ? json_encode($meta) : ''));
    }

    public function cloneTo(Snapshot $snapshot): Checkpoint
    {
        $checkpoint = $this->replicate();
        $checkpoint->id = self::getId($snapshot->id, $checkpoint->name, $checkpoint->meta);
        $checkpoint->snapshot()->associate($snapshot);
        return $checkpoint;
    }

    /**
     * Reset diff for checkpoint.
     */
    public function resetDiff() : void
    {
        $this->diff_url = null;
        $this->diff_status = self::DIFF_STATUS_UNKNOWN;
        $this->status = self::STATUS_UNKNOWN;
    }

    /**
     * Maybe update status from diff_status.
     */
    public function updateStatusFromDiff() : void
    {
        // If the diff is identical, automatically approve this checkpoint.
        if ($this->diff_status == self::DIFF_STATUS_IDENTICAL) {
            $this->status = self::STATUS_APPROVED;
        }
    }

    /**
     * Update checkpoints with new diff.
     *
     * @param string $image_url
     *   Image URL.
     * @param string $baseline_url
     *   Baseline URL.
     * @param string $diff_url
     *   Diff URL.
     * @param bool $different
     *   Whether the image and baseline differ.
     */
    public static function updateDiffs(string $image_url, string $baseline_url, string $diff_url, bool $different) : void
    {
        // Get all the checkpoints for this image and baseline combination.
        // Weed out those with the same diff (we assume they've already been
        // updated) and those which have been approved/rejected/ignored
        // (doesn't make sense to update those).
        $checkpoints = self::where(['image_url' => $image_url, 'baseline_url' => $baseline_url])
            ->whereNested(function ($query) use ($diff_url) {
                $query->where('diff_url')->where('diff_url', '<>', $diff_url, 'or');
            })
            ->where('status', self::STATUS_UNKNOWN)
            ->get();

        foreach ($checkpoints as $checkpoint) {
            $checkpoint->diff_url = $diff_url;
            $checkpoint->diff_status = $different ?
                self::DIFF_STATUS_DIFFERENT :
                self::DIFF_STATUS_IDENTICAL;
            $checkpoint->save();
        }
    }

    /**
     * Does checkpoint have a diff.
     */
    public function hasDiff() : bool
    {
        return $this->diff_status !== self::DIFF_STATUS_UNKNOWN;
    }

    /**
     * Should this propagate to later snapshots?
     *
     * Unless it's an approved removal, it should.
     */
    public function shouldPropagate() : bool
    {
        return !(empty($this->image_url) && $this->status == self::STATUS_APPROVED);
    }

    /**
     * Approve checkpoint.
     */
    public function approve()
    {
        if ($this->isPending()) {
            return;
        }
        $this->status = self::STATUS_APPROVED;
        $this->save();
    }

    /**
     * Reject checkpoint.
     */
    public function reject()
    {
        if ($this->isPending()) {
            return;
        }
        $this->status = self::STATUS_REJECTED;
        $this->save();
    }

    /**
     * Ignore checkpoint.
     */
    public function ignore()
    {
        if ($this->isPending()) {
            return;
        }
        $this->status = self::STATUS_IGNORED;
        $this->save();
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }
}
