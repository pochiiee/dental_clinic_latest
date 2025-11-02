<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedbacks extends Model
{
    use HasFactory;

    protected $primaryKey = 'feedback_id';

    protected $fillable = [
        'user_id',
        'message',
        'image_url',
    ];

    /**
     * Get the user that owns the feedback.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the image URL attribute with full path
     */
     public function getImageUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // If it's already a full URL, return as is
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // If it starts with storage/, return it as is (let Vue handle the path)
        if (str_starts_with($value, 'storage/')) {
            return $value;
        }

        // If it's just a filename, assume it's in storage/feedbacks/
        return 'feedbacks/' . $value;
    }
}