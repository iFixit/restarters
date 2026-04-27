<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

class Skills extends Model
{
    protected $table = 'skills';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['skill_name', 'category', 'description'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    //Table Relations

    // Setters

    public function setSkillNameAttribute($value)
    {
        $this->attributes['skill_name'] = $value === null ? null : strip_tags((string) $value);
    }

    //Getters
}
