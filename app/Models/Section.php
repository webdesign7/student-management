<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'class_id'];

    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
