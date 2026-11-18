<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kegiatan = satu daftar bawaan (mis. "Hiking Gunung", "Sekolah Senin", "Liburan Bali").
 * trip_date = tanggal perjalanan yang sedang berjalan. Scan berangkat dan pulang
 * dikelompokkan per (kegiatan + trip_date), jadi kegiatan yang sama bisa dipakai
 * berulang lewat tombol "Mulai baru".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('trip_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('activity_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            $table->unique(['activity_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_items');
        Schema::dropIfExists('activities');
    }
};
