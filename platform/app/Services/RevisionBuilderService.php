<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Exam;
use App\Models\LearningMaterial;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RevisionBuilderService
{
    public function __construct(
        private readonly QuestionResourceSnapshotService $resourceSnapshots,
    ) {}

    public function createDraft(Model $source, User $author, array $classIds = []): Revision
    {
        abort_unless((int) $source->organization_id === (int) $author->organization_id, 403);

        return DB::transaction(function () use ($source, $author, $classIds): Revision {
            $revision = Revision::create([
                'organization_id' => $author->organization_id,
                'author_id' => $author->id,
                'discipline_id' => $source->discipline_id ?? null,
                'title' => 'Revisão — '.($source->title ?? data_get($source, 'content.statement', 'conteúdo')),
                'description' => 'Rascunho criado a partir de '.class_basename($source).'. Revise antes de publicar.',
                'status' => 'draft',
            ]);

            $revision->sources()->create([
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
            ]);
            $revision->schoolClasses()->sync($classIds);

            $this->seedItems($revision, $source, $author);

            return $revision->load(['items', 'sources', 'schoolClasses']);
        });
    }

    private function seedItems(Revision $revision, Model $source, User $author): void
    {
        if ($source instanceof Exam || $source instanceof Activity) {
            $source->loadMissing([
                'questions.resourceLinks.resource',
                'questions.resourceLinks.version',
            ]);
            foreach ($source->questions as $index => $question) {
                $this->addQuestion($revision, $question, $author, $index);
            }

            return;
        }

        if ($source instanceof Question) {
            $source->loadMissing([
                'resourceLinks.resource',
                'resourceLinks.version',
            ]);
            $this->addQuestion($revision, $source, $author, 0);

            return;
        }

        $body = $source instanceof LearningMaterial
            ? ($source->body ?: $source->description)
            : ($source instanceof Lesson ? $source->content : null);

        if (filled($body)) {
            $revision->items()->create([
                'type' => 'explanation',
                'order' => 0,
                'prompt' => $source->title,
                'content' => ['body' => $body],
                'solution' => [],
                'points' => 0,
                'updated_by' => $author->id,
            ]);
        }
    }

    private function addQuestion(Revision $revision, Question $question, User $author, int $order): void
    {
        $content = $question->content ?? [];
        $type = in_array($question->type, ['multiple_choice', 'true_false'], true)
            ? $question->type
            : 'short_answer';

        $solution = $type === 'short_answer'
            ? ['accepted_answers' => array_values(array_filter([(string) ($content['expected_answer'] ?? '')]))]
            : ['correct_option' => $content['correct_option'] ?? null];

        $revision->items()->create([
            'type' => $type,
            'order' => $order,
            'prompt' => $content['statement'] ?? 'Questão sem enunciado',
            'content' => [
                'options' => $content['options'] ?? [],
                'resources' => $this->resourceSnapshots->forQuestion($question, true),
            ],
            'solution' => $solution,
            'explanation' => $content['feedback'] ?? null,
            'points' => (float) ($question->pivot?->points ?? 1),
            'updated_by' => $author->id,
        ]);
    }
}
