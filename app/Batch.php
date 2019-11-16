<?php

declare(strict_types=1);

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webpatser\Uuid\Uuid;

class Batch extends Model
{
    /**
     * We don't use an incrementing key.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The type of key we use.
     *
     * @var string
     */
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
    public static function boot(): void
    {
        // If more models use this, make a trait as suggested in:
        // https://medium.com/@steveazz/setting-up-uuids-in-laravel-5-552412db2088
        parent::boot();
        self::creating(static function ($model): void {
            $model->{$model->getKeyName()} = (string) Uuid::generate(4);
        });
    }

    /**
     * Get the snapshot associated with this batch.
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo('Appocular\Assessor\Snapshot');
    }
}
