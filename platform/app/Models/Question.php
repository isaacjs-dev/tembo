<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'owner_id',
        'type',
        'content',
        'visibility_scope',
        'source_question_id',
        'knowledge_area_id',
        'discipline_id',
        'level',
        'stage',
        'grade',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sourceQuestion()
    {
        return $this->belongsTo(Question::class, 'source_question_id');
    }

    public function shares()
    {
        return $this->hasMany(QuestionShare::class);
    }

    public function knowledgeArea()
    {
        return $this->belongsTo(KnowledgeArea::class);
    }

    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }

    public function bnccSkills()
    {
        return $this->belongsToMany(BNCcNode::class, 'question_bncc_links', 'question_id', 'bncc_skill_node_id')->withTimestamps();
    }

    public function customSkills()
    {
        return $this->belongsToMany(CustomSkill::class, 'question_custom_skill', 'question_id', 'custom_skill_id')->withTimestamps();
    }

    public function revisionSources()
    {
        return $this->morphMany(RevisionSource::class, 'source');
    }
}
