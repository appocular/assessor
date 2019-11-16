<?php

declare(strict_types=1);

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;
use Webpatser\Uuid\Uuid;

class Repo extends Model
{
    /**
     * Tell Eloquent the name of our primary key.
     *
     * @var string
     */
    public $primaryKey = 'uri';

    /**
     * Tell Eloquent which properties are fillable.
     *
     * @var array<string>
     */
    protected $fillable = ['uri'];

    /**
     * Tell Eloquent that our key aren't incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tell Eloquent the type of our key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     *  Setup model event hooks to generate token.
     */
    public static function boot(): void
    {
        parent::boot();
        self::creating(static function ($model): void {
            if ($model->api_token) {
                return;
            }

            // The database schema will enforce uniqueness of token, so
            // we'll just regenerate on the (extremely unlikely) chance we
            // hit an existing.
            do {
                $model->api_token = \hash('sha256', $model->uri . (string) Uuid::generate(4));
            } while ($model->where('api_token', $model->api_token)->exists());
        });
    }
}
