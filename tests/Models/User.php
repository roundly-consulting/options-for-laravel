<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use RoundlyConsulting\Options\Traits\HasOptions;

class User extends Authenticatable
{
    use HasOptions;

    protected $guarded = [];

    public $timestamps = false;
}
