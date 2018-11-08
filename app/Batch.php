<?php

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;
use Webpatser\Uuid\Uuid;

class Batch extends Model
{

    protected $fillable = ['sha'];

    /**
     * We don't use an incrementing key.
     */
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'batches';

    /**
     *  Setup model event hooks
     */
    public static function boot()
    {
        // If more models use this, make a trait as suggested in:
        // https://medium.com/@steveazz/setting-up-uuids-in-laravel-5-552412db2088
        parent::boot();
        self::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Uuid::generate(4);
        });
    }

    /**
     * Get the commit associated with this batch.
     */
    public function commit()
    {
        return $this->belongsTo('Appocular\Assessor\Commit', 'sha');
    }
}
