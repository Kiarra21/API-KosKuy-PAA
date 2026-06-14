<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class CheckInOut extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'check_in_outs';

    protected $fillable = [
        'booking_id',
        'handled_by',
        'checked_in_at',
        'checked_out_at',
        'check_in_photo',
        'check_out_photo',
        'notes',
    ];

    protected $casts = [
        'booking_id' => 'integer',
        'handled_by' => 'integer',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    protected $appends = [
        'check_in_photo_url',
        'check_out_photo_url',
    ];

    public function getCheckInPhotoUrlAttribute(): ?string
    {
        return $this->check_in_photo ? $this->publicStorageUrl($this->check_in_photo) : null;
    }

    public function getCheckOutPhotoUrlAttribute(): ?string
    {
        return $this->check_out_photo ? $this->publicStorageUrl($this->check_out_photo) : null;
    }

    private function publicStorageUrl(string $path): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
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
