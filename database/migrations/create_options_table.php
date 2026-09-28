<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('options.key_type');

        Schema::create('options', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->morphKey('owner', $keyType, nullable: true);
            // `global` or a hash of owner type + id, filled by the model. The unique
            // index below needs it: a global option's NULL owner columns never collide.
            $table->string('owner_scope', 32);
            $table->string('key')->index();
            $table->text('value')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id', 'key']);
            $table->unique(['owner_scope', 'key']);
        });
    }
};
