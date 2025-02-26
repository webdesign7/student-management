<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kalnoy\Nestedset\NodeTrait;
use \Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Subject extends Model
{
    use NodeTrait;

    //use HasRecursiveRelationships;

    public $timestamps = false;

    protected $fillable = ['parent_id', 'name', 'sort', '_lft', '_rgt'];

    public function ancestors()
    {
        return $this->belongsToMany(static::class, 'subjects', 'id', 'parent_id')
            ->withPivot(['_lft', '_rgt']);
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')
            ->orderBy('subjects.sort')
            ->with('children');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function getChildrenAttribute()
    {
        return $this->children()->get();
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'root_subject_id');
    }
}
