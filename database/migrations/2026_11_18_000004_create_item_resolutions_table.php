<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barang yang dibawa saat berangkat tapi tidak kembali saat scan pulang:
 * pengguna menjelaskan "dipinjam/dititipkan" (lent) atau "hilang" (lost).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20); // 'lent' | 'lost'
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'item_id', 'activity_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_resolutions');
    }
};
