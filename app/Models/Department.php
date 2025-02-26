<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kalnoy\Nestedset\NodeTrait;

class Department extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'root_subject_id'];

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'id', 'root_subject_id')
            ->with('children.children.children')
            ->orderBy('sort');
    }

    public function rootSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'root_subject_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Subject::class, 'id', 'root_subject_id')
            ->whereNotNull('parent_id')
            ->orderBy('sort');
    }
}
