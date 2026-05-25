<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'address',
        'longitude',
        'latitude',
        'phone',
        'qris_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'qris_code_url',
    ];

    public function getQrisCodeUrlAttribute(): ?string
    {
        return $this->qris_code ? Storage::disk('public')->url($this->qris_code) : null;
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'branch_facility')->withTimestamps();
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BranchPhoto::class)->orderBy('order');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
