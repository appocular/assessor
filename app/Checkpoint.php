<?php

namespace Appocular\Assessor;

use Appocular\Assessor\Events\CheckpointUpdated;
use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{
    const STATUS_UNKNOWN = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;
    const STATUS_IGNORED = 3;

    const DIFF_STATUS_UNKNOWN = 0;
    const DIFF_STATUS_IDENTICAL = 1;
    const DIFF_STATUS_DIFFERENT = 2;

    protected $fillable = ['id', 'name', 'snapshot_id', 'image_sha', 'baseline_sha', 'diff_sha'];
    protected $visible = ['id', 'name', 'image_sha', 'baseline_sha', 'diff_sha'];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $dispatchesEvents = [
        'updated' => CheckpointUpdated::class,
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
            ->where([['snapshot_id', $snapshotId], ['image_sha', null]])
            ->delete();
        $checkpoint->newModelQuery()
            ->where('snapshot_id', $snapshotId)
            ->update([
                'baseline_sha' => null,
                'diff_sha' => null,
                'status' => self::STATUS_UNKNOWN,
                'diff_status' => self::DIFF_STATUS_UNKNOWN,
            ]);
    }

    /**
     * Update checkpoints with new diff.
     *
     * @param string $image_sha
     *   Image sha.
     * @param string $baseline_sha
     *   Baseline sha.
     * @param string $diff_sha
     *   Diff sha.
     * @param bool $different
     *   Whether the image and baseline differ.
     */
    public static function updateDiffs(string $image_sha, string $baseline_sha, string $diff_sha, bool $different)
    {
        // Get all the checkpoints for this image and baseline combination.
        // Weed out those with the same diff (we assume they've already been
        // updated) and those which have been approved/rejected/ignored
        // (doesn't make sense to update those).
        $checkpoints = self::where(['image_sha' => $image_sha, 'baseline_sha' => $baseline_sha])
            ->whereNested(function ($query) use ($diff_sha) {
                $query->where('diff_sha')->where('diff_sha', '<>', $diff_sha, 'or');
            })
            ->where('status', self::STATUS_UNKNOWN)
            ->get();

        foreach ($checkpoints as $checkpoint) {
            $checkpoint->diff_sha = $diff_sha;
            $checkpoint->diff_status = $different ?
                self::DIFF_STATUS_DIFFERENT :
                self::DIFF_STATUS_IDENTICAL;
            $checkpoint->save();
        }
    }

    /**
     * Should this propagate to later snapshots?
     *
     * Unless it's an approved removal, it should.
     */
    public function shouldPropagate() : bool
    {
        return !(empty($this->image_sha) && $this->status == self::STATUS_APPROVED);
    }
}
