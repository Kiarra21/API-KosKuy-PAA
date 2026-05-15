<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'branch_facility')->withTimestamps();
    }
}
