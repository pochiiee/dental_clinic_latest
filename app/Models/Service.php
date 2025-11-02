<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Fluent\Concerns\Has;

class Service extends Model
{
    use HasFactory;

    protected $table = 'services';
    protected $primaryKey = 'service_id';
    
    protected $fillable = [
        'service_name',
        'description',
    ];

        public function tools()
    {
        return $this->belongsToMany(Tool::class, 'service_tools', 'service_id', 'tool_id');
    }

        public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

}
