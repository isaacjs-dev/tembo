<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use App\Services\OmrGradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmrGradingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_grades_an_objective_answer_using_the_exam_points(): void
    {
        $organization = Organization::create([
            'name' => 'Escola OMR',
            'active' => true,
        ]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $question = Question::create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Quanto é dois mais dois?',
                'options' => ['3', '4', '5'],
                'correct_option' => 1,
            ],
            'visibility_scope' => 'private',
        ]);
        $exam = Exam::create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação objetiva',
            'status' => 'published',
            'settings' => [],
        ]);
        $exam->questions()->attach($question->id, [
            'points' => 2.5,
            'order' => 1,
        ]);

        $result = app(OmrGradingService::class)->gradeAnswers(
            $exam->id,
            null,
            [$question->id => 1],
        );

        $this->assertSame(2.5, (float) $result['score']);
        $this->assertSame(2.5, (float) $result['total_points']);
        $this->assertTrue($result['details'][$question->id]['correct']);
        $this->assertSame('graded', $result['details'][$question->id]['status']);
    }
}
