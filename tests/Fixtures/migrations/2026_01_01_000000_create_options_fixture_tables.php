<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host-owned entity options hang off. `owner` is a polymorphic, deliberately
 * unconstrained `nullableMorphs`, so the owner's table is the host's business and nothing
 * here is shipped schema with an order to pin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->id());
    }
};
