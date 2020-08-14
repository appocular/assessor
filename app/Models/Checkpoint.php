<?php

declare(strict_types=1);

namespace Appocular\Assessor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkpoint extends Model
{
    public const IMAGE_STATUS_PENDING = 'pending';
    public const IMAGE_STATUS_EXPECTED = 'expected';
    public const IMAGE_STATUS_AVAILABLE = 'available';

    public const DIFF_STATUS_UNKNOWN = 'unknown';
    public const DIFF_STATUS_IDENTICAL = 'identical';
    public const DIFF_STATUS_DIFFERENT = 'different';

    public const APPROVAL_STATUS_UNKNOWN = 'unknown';
    public const APPROVAL_STATUS_APPROVED = 'approved';
    public const APPROVAL_STATUS_REJECTED = 'rejected';
    public const APPROVAL_STATUS_IGNORED = 'ignored';

    /**
     * Tell Eloquent which properties are fillable.
     *
     * @var array<string>
     */
    protected $fillable = ['id', 'name', 'snapshot_id', 'image_url', 'baseline_url', 'diff_url', 'meta'];

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
     * Tell Eloquent how to cast columns.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Store meta as JSON in the database.
        'meta' => 'array',
    ];

    /**
     * Get the snapshot for the checkpoint.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo('Appocular\Assessor\Models\Snapshot');
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
    public static function resetBaselines(string $snapshotId): void
    {
        $checkpoint = new static();
        $checkpoint->newModelQuery()
            ->where([
                ['snapshot_id', $snapshotId],
                ['image_status', self::IMAGE_STATUS_EXPECTED],
            ])
            ->delete();

        $checkpoint->newModelQuery()
            ->where([
                ['snapshot_id', $snapshotId],
            ])
            ->update([
                'baseline_url' => null,
                'diff_url' => null,
                'approval_status' => self::APPROVAL_STATUS_UNKNOWN,
                'diff_status' => self::DIFF_STATUS_UNKNOWN,
            ]);
    }

    /**
     * Sorts meta data to the canonical representation.
     *
     * @param array<string, string> $meta
     *
     * @return array<string, string>
     */
    public static function cleanMeta(array $meta): array
    {
        \ksort($meta);

        return $meta;
    }

    /**
     * @param array<string, string> $meta
     */
    public static function getId(string $snapshotId, string $name, ?array $meta = null): string
    {
        return \hash('sha256', $snapshotId . $name . ($meta ? \json_encode($meta) : ''));
    }

    public function identifier(): string
    {
        return $this->name . ($this->meta ? \json_encode($this->meta) : '');
    }

    public function cloneTo(Snapshot $snapshot): Checkpoint
    {
        $checkpoint = $this->replicate();
        $checkpoint->id = self::getId($snapshot->id, $checkpoint->name, $checkpoint->meta);
        $checkpoint->snapshot()->associate($snapshot);

        return $checkpoint;
    }

    public function createExpected(Snapshot $snapshot): Checkpoint
    {
        $checkpoint = new self([
            'id' => self::getId($snapshot->id, $this->name, $this->meta),
            'name' => $this->name,
            'meta' => $this->meta,
        ]);
        // Status is not fillable, so set it afterwards.
        // TODO: use empty $guard on models?
        $checkpoint->image_status = Checkpoint::IMAGE_STATUS_EXPECTED;

        $snapshot->checkpoints()->save($checkpoint);

        return $checkpoint;
    }

    /**
     * Reset diff for checkpoint.
     */
    public function resetDiff(): void
    {
        $this->diff_url = null;
        $this->diff_status = self::DIFF_STATUS_UNKNOWN;
        $this->approval_status = self::APPROVAL_STATUS_UNKNOWN;
    }

    /**
     * Maybe update status from diff_status.
     */
    public function updateStatusFromDiff(): void
    {
        // If the diff is identical, automatically approve this checkpoint.
        if ($this->diff_status !== self::DIFF_STATUS_IDENTICAL) {
            return;
        }

        $this->approval_status = self::APPROVAL_STATUS_APPROVED;
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
    public static function updateDiffs(string $image_url, string $baseline_url, string $diff_url, bool $different): void
    {
        // Get all the checkpoints for this image and baseline combination.
        // Weed out those with the same diff (we assume they've already been
        // updated) and those which have been approved/rejected/ignored
        // (doesn't make sense to update those).
        $checkpoints = self::where(['image_url' => $image_url, 'baseline_url' => $baseline_url])
            ->whereNested(static function ($query) use ($diff_url): void {
                $query->where('diff_url')->where('diff_url', '<>', $diff_url, 'or');
            })
            ->where('approval_status', self::APPROVAL_STATUS_UNKNOWN)
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
    public function hasDiff(): bool
    {
        return $this->diff_status !== self::DIFF_STATUS_UNKNOWN;
    }

    /**
     * Should this propagate to later snapshots?
     *
     * Unless it's an approved removal, it should.
     */
    public function shouldPropagate(): bool
    {
        // While '0' is technically not quite the same as '' or null, it's
        // also an invalid URL, so we don't care.
        return $this->image_url || $this->approval_status === self::APPROVAL_STATUS_APPROVED;
    }

    /**
     * Approve checkpoint.
     */
    public function approve(): void
    {
        if ($this->isPending()) {
            return;
        }

        $this->approval_status = self::APPROVAL_STATUS_APPROVED;
        $this->save();
    }

    /**
     * Reject checkpoint.
     */
    public function reject(): void
    {
        if ($this->isPending()) {
            return;
        }

        $this->approval_status = self::APPROVAL_STATUS_REJECTED;
        $this->save();
    }

    /**
     * Ignore checkpoint.
     */
    public function ignore(): void
    {
        if ($this->isPending()) {
            return;
        }

        $this->approval_status = self::APPROVAL_STATUS_IGNORED;
        $this->save();
    }

    public function isPending(): bool
    {
        return $this->image_status !== self::IMAGE_STATUS_AVAILABLE;
    }
}
