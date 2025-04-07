<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chart_requests', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_accessed_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_requests');
    }
};
