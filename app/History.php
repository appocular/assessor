<?php

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;

class History extends Model
{

    protected $fillable = ['snapshot_id', 'history',];
    protected $visible = ['snapshot_id', 'history'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'history';
    public $timestamps = false;

    /**
     * Get the snapshot for the history.
     */
    public function snapshot()
    {
        return $this->belongsTo('Appocular\Assessor\Snapshot');
    }
}
