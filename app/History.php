<?php

declare(strict_types=1);

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class History extends Model
{
    /**
     * Tell Eloquent which properties are fillable.
     *
     * @var array<string>
     */
    protected $fillable = ['snapshot_id', 'history',];

    /**
     * Tell Eloquent that our key isn't incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tell Eloquent what our primary key is.
     *
     * @var string
     */
    protected $primaryKey = 'snapshot_id';

    /**
     * Tell Eloquent the type of our key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Tell Eloquent the name of our table.
     *
     * Else it would auto-generate "Historys".
     *
     * @var string
     */
    protected $table = 'history';

    /**
     * Tell Eloquent that we don't need timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the snapshot for the history.
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo('Appocular\Assessor\Snapshot');
    }
}
