<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected $fillable = [
        'organization_id',
        'author_id',
        'title',
        'status',
        'access_code',
        'settings',
        'answer_sheet_type_slug',
        'version',
        'card_template_id',
        'card_template_version',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'version' => 'integer',
            'card_template_version' => 'integer',
        ];
    }

    public function cardTemplate()
    {
        return $this->belongsTo(OmrTemplate::class, 'card_template_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot('points', 'order')
            ->withTimestamps()
            ->orderBy('exam_questions.order');
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'exam_school_class')
            ->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }

    public function copies()
    {
        return $this->hasMany(ExamCopy::class);
    }

    public function revisionSources()
    {
        return $this->morphMany(RevisionSource::class, 'source');
    }
}
