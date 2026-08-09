<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionItem extends Model
{
    public const TYPES = ['multiple_choice', 'true_false', 'matching', 'fill_blank', 'ordering', 'flashcard', 'short_answer', 'explanation', 'example'];

    protected $fillable = ['revision_id', 'custom_skill_id', 'bncc_node_id', 'type', 'order', 'difficulty', 'prompt', 'content', 'solution', 'explanation', 'hints', 'points', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return ['content' => 'array', 'solution' => 'array', 'hints' => 'array', 'points' => 'decimal:2', 'is_active' => 'boolean', 'difficulty' => 'integer'];
    }

    public function revision()
    {
        return $this->belongsTo(Revision::class);
    }

    public function customSkill()
    {
        return $this->belongsTo(CustomSkill::class);
    }

    public function bnccNode()
    {
        return $this->belongsTo(BNCcNode::class);
    }
}
