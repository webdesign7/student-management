<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kalnoy\Nestedset\NodeTrait;
use \Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Subject extends Model
{
    use NodeTrait;

    //use HasRecursiveRelationships;

    public $timestamps = false;

    protected $fillable  = ['parent_id', 'name', 'sort']; // or whatever your columns are

    // Add this method to make it compatible with adjacency list expectations
    public function children()
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    // Add this to maintain compatibility with the adjacency list component
    public function getChildrenAttribute()
    {
        return $this->children()->get();
    }

    // Optional: Add this if you need to query ancestors
    public function parent()
    {
        return $this->belongsTo(static::class, 'parent_id');
    }
}
