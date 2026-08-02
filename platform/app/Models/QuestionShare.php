<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'shared_with_user_id',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }
}
