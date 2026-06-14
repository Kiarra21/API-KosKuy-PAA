<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

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

    protected $appends = ['display_status'];

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return 'Dibatalkan';
        }
        if ($this->status === 'completed') {
            return 'Selesai';
        }

        $payment = $this->payment;

        if ($payment) {
            if ($payment->status === 'paid') {
                if ($this->status === 'confirmed') {
                    return 'Dikonfirmasi';
                }
                return 'Lunas';
            }
            if ($payment->status === 'pending') {
                return 'Menunggu Verifikasi';
            }
        }

        return 'Belum Bayar';
    }

    protected $casts = [
        'user_id' => 'integer',
        'room_type_id' => 'integer',
        'room_id' => 'integer',
        'assigned_by' => 'integer',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'assigned_at' => 'datetime',
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

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function checkInOut(): HasOne
    {
        return $this->hasOne(CheckInOut::class);
    }
}
