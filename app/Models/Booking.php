<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_type_id',
        'room_id',
        'assigned_by',
        'assigned_at',
        'check_in_date',
        'check_out_date',
        'total_nights',
        'total_price',
        'status',
        'notes',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'room_type_id' => 'integer',
        'room_id' => 'integer',
        'assigned_by' => 'integer',
        'assigned_at' => 'datetime',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'total_nights' => 'integer',
        'total_price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
