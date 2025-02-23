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


}
