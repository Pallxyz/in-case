<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['success', 'failed'])->default('success');
            // 'packing' = scan berangkat, 'return' = scan pulang
            $table->string('phase', 20)->default('packing');
            // Salinan activities.trip_date saat scan terjadi.
            $table->date('for_date');
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['user_id', 'activity_id', 'for_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
