<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    use HasFactory;

    protected $table = 'tools';
    protected $primaryKey = 'tool_id';

    protected $fillable = ['tool_name'];

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_tools', 'tool_id', 'service_id');
    }
}
