<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemResolution extends Model
{
    public const LENT = 'lent'; // dipinjam / dititipkan / terbawa teman
    public const LOST = 'lost'; // memang hilang

    protected $fillable = ['user_id', 'item_id', 'activity_id', 'date', 'status', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
