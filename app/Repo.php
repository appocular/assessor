<?php

namespace Appocular\Assessor;

use Illuminate\Database\Eloquent\Model;
use Webpatser\Uuid\Uuid;

class Repo extends Model
{
    public $primaryKey = 'uri';

    protected $fillable = ['uri'];

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     *  Setup model event hooks to generate token.
     */
    public static function boot()
    {
        parent::boot();
        self::creating(function ($model) {
            if (empty($model->token)) {
                $model->token = hash('sha256', $model->uri . (string) Uuid::generate(4));
            }
        });
    }
}
