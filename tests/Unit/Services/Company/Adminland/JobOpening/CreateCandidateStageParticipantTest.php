<?php

namespace Tests\Unit\Services\Company\Adminland\JobOpening;

use Tests\TestCase;
use App\Jobs\LogAccountAudit;
use App\Models\Company\Employee;
use App\Models\Company\Candidate;
use App\Models\Company\JobOpening;
use Illuminate\Support\Facades\Queue;
use App\Models\Company\CandidateStage;
use Illuminate\Validation\ValidationException;
use App\Models\Company\CandidateStageParticipant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\Company\Adminland\JobOpening\CreateCandidateStageParticipant;

class CreateCandidateStageParticipantTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_creates_a_candidate_stage_participant_as_administrator(): void
    {
        $michael = $this->createAdministrator();
        $opening = JobOpening::factory()->create([
            'company_id' => $michael->company_id,
        ]);
        $candidate = Candidate::factory()->create([
            'company_id' => $michael->company_id,
            'job_opening_id' => $opening->id,
        ]);
        $candidateStage = CandidateStage::factory()->create([
            'candidate_id' => $candidate->id,
            'stage_position' => 1,
        ]);
        $participant = Employee::factory()->create([
            'company_id' => $michael->company_id,
        ]);

        $this->executeService($michael, $opening, $candidate, $candidateStage, $participant);
    }

    /** @test */
    public function it_creates_a_candidate_stage_participant_as_hr(): void
    {
        $michael = $this->createHR();
        $opening = JobOpening::factory()->create([
            'company_id' => $michael->company_id,
        ]);
        $candidate = Candidate::factory()->create([
            'company_id' => $michael->company_id,
            'job_opening_id' => $opening->id,
        ]);
        $candidateStage = CandidateStage::factory()->create([
            'candidate_id' => $candidate->id,
            'stage_position' => 1,
        ]);
        $participant = Employee::factory()->create([
            'company_id' => $michael->company_id,
        ]);

        $this->executeService($michael, $opening, $candidate, $candidateStage, $participant);
    }

    /** @test */
    public function it_creates_a_candidate_stage_participant_as_sponsor(): void
    {
        $michael = $this->createEmployee();
        $opening = JobOpening::factory()->create([
            'company_id' => $michael->company_id,
        ]);
        $opening->sponsors()->sync([$michael->id]);
        $candidate = Candidate::factory()->create([
            'company_id' => $michael->company_id,
            'job_opening_id' => $opening->id,
        ]);
        $candidateStage = CandidateStage::factory()->create([
            'candidate_id' => $candidate->id,
            'stage_position' => 1,
        ]);
        $participant = Employee::factory()->create([
            'company_id' => $michael->company_id,
        ]);

        $this->executeService($michael, $opening, $candidate, $candidateStage, $participant);
    }

    /** @test */
    public function normal_user_cant_execute_the_service(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $michael = $this->createEmployee();
        $opening = JobOpening::factory()->create([
            'company_id' => $michael->company_id,
        ]);
        $candidate = Candidate::factory()->create([
            'company_id' => $michael->company_id,
            'job_opening_id' => $opening->id,
        ]);
        $candidateStage = CandidateStage::factory()->create([
            'candidate_id' => $candidate->id,
            'stage_position' => 1,
        ]);
        $participant = Employee::factory()->create([
            'company_id' => $michael->company_id,
        ]);

        $this->executeService($michael, $opening, $candidate, $candidateStage, $participant);
    }

    /** @test */
    public function it_fails_if_wrong_parameters_are_given(): void
    {
        $request = [
            'title' => 'Assistant to the regional manager',
        ];

        $this->expectException(ValidationException::class);
        (new CreateCandidateStageParticipant)->execute($request);
    }

    /** @test */
    public function it_fails_if_job_opening_doesnt_belong_to_company(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $michael = $this->createAdministrator();
        $opening = JobOpening::factory()->create([]);
        $candidate = Candidate::factory()->create([
            'company_id' => $michael->company_id,
            'job_opening_id' => $opening->id,
        ]);
        $candidateStage = CandidateStage::factory()->create([
            'candidate_id' => $candidate->id,
            'stage_position' => 1,
        ]);
        $participant = Employee::factory()->create([
            'company_id' => $michael->company_id,
        ]);

        $this->executeService($michael, $opening, $candidate, $candidateStage, $participant);
    }

    private function executeService(Employee $author, JobOpening $opening, Candidate $candidate, CandidateStage $candidateStage, Employee $participant): void
    {
        Queue::fake();

        $candidateStageParticipant = (new CreateCandidateStageParticipant)->execute([
            'company_id' => $author->company_id,
            'author_id' => $author->id,
            'job_opening_id' => $opening->id,
            'candidate_id' => $candidate->id,
            'candidate_stage_id' => $candidateStage->id,
            'participant_id' => $participant->id,
        ]);

        $this->assertDatabaseHas('candidate_stage_participants', [
            'id' => $candidateStageParticipant->id,
            'participant_id' => $participant->id,
            'candidate_stage_id' => $candidateStage->id,
            'participant_name' => $participant->name,
        ]);

        $this->assertInstanceOf(
            CandidateStageParticipant::class,
            $candidateStageParticipant
        );

        Queue::assertPushed(LogAccountAudit::class, function ($job) use ($author, $opening, $participant, $candidate) {
            return $job->auditLog['action'] === 'candidate_stage_participant_created' &&
                $job->auditLog['author_id'] === $author->id &&
                $job->auditLog['objects'] === json_encode([
                    'job_opening_id' => $opening->id,
                    'job_opening_title' => $opening->title,
                    'job_opening_reference_number' => $opening->reference_number,
                    'candidate_id' => $candidate->id,
                    'candidate_name' => $candidate->name,
                    'participant_id' => $participant->id,
                    'participant_name' => $participant->name,
                ]);
        });
    }
}
