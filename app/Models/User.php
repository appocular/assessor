<?php

declare(strict_types=1);

namespace Appocular\Assessor\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;

    /**
     * Tell Eloquent which properties are fillable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name', 'email',
    ];

    /**
     * Tell Eloquent which properties are hidden in JSON.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
    ];
}
