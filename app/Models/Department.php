<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kalnoy\Nestedset\NodeTrait;

class Department extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class)->whereNull('parent_id')->with('children')->orderBy('sort');
    }
}
