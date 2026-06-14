<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'code',
        'status',
        'proof_image',
        'rejection_reason',
        'handled_by',
        'handled_at',
        'paid_at',
    ];

    protected $casts = [
        'booking_id' => 'integer',
        'handled_by' => 'integer',
        'handled_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected $appends = [
        'proof_image_url',
    ];

    public function getProofImageUrlAttribute(): ?string
    {
        return $this->proof_image ? Storage::disk('public')->url($this->proof_image) : null;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
