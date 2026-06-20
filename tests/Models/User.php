<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\Traits\HasOptions;

class User extends Model
{
    use HasOptions;

    protected $guarded = [];

    public $timestamps = false;
}
