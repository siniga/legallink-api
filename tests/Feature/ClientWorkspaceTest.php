<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\Firm;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        return $app;
    }

    private function clientContext(): array
    {
        $firm = Firm::create(['name' => 'Test Firm', 'slug' => 'test-firm']);
        $user = User::factory()->create(['firm_id' => $firm->id]);
        Sanctum::actingAs($user);
        $client = Client::create(['name' => 'Acacia Trading', 'type' => 'company']);
        return [$user, $client];
    }

    public function test_created_case_task_and_upload_appear_on_the_client(): void
    {
        [$user, $client] = $this->clientContext();
        Storage::fake('local');
        $case = $this->postJson('/api/cases', [
            'title' => 'Contract review', 'case_number' => 'TEST-001', 'client_id' => $client->id,
        ])->assertCreated()->json('data');
        $task = $this->postJson('/api/tasks', [
            'title' => 'Review agreement', 'client_id' => $client->id, 'case_id' => $case['id'],
            'assignee_id' => $user->id, 'due_date' => '2026-09-20',
        ])->assertCreated()->json('data');
        $this->post('/api/documents', [
            'client_id' => $client->id, 'case_id' => $case['id'], 'visibility' => 'firm',
            'files' => [UploadedFile::fake()->create('agreement.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->getJson('/api/clients/'.$client->id)->assertOk()
            ->assertJsonPath('cases.0.id', $case['id'])
            ->assertJsonPath('cases.0.is_closed', false)
            ->assertJsonPath('tasks.0.id', $task['id'])
            ->assertJsonPath('tasks.0.case_title', 'Contract review')
            ->assertJsonPath('documents.0.name', 'agreement.pdf');
        $this->putJson('/api/tasks/'.$task['id'], ['status' => 'completed'])->assertOk();
        $this->getJson('/api/clients/'.$client->id)->assertOk()->assertJsonPath('tasks.0.status', 'completed');
    }

    public function test_client_workspace_excludes_other_clients_tasks_and_hidden_documents(): void
    {
        [$user, $client] = $this->clientContext();
        $otherClient = Client::create(['name' => 'Other Client', 'type' => 'company']);
        Task::create(['title' => 'Other client work', 'client_id' => $otherClient->id]);
        $task = Task::create(['title' => 'This client work', 'client_id' => $client->id]);
        $otherUser = User::factory()->create(['firm_id' => $user->firm_id]);
        Document::create(['name' => 'Private.pdf', 'client_id' => $client->id, 'owner_id' => $otherUser->id, 'visibility' => 'private', 'kind' => 'pdf', 'is_folder' => false]);
        Document::create(['name' => 'Shared.pdf', 'client_id' => $client->id, 'owner_id' => $otherUser->id, 'visibility' => 'firm', 'kind' => 'pdf', 'is_folder' => false]);
        $this->getJson('/api/clients/'.$client->id)->assertOk()
            ->assertJsonCount(1, 'tasks')->assertJsonPath('tasks.0.id', $task->id)
            ->assertJsonCount(1, 'documents')->assertJsonPath('documents.0.name', 'Shared.pdf');
        $otherFirm = Firm::create(['name' => 'Other Firm', 'slug' => 'other-firm']);
        $foreignClient = Client::create(['name' => 'Foreign Client', 'type' => 'company', 'firm_id' => $otherFirm->id]);
        $this->getJson('/api/clients/'.$foreignClient->id)->assertNotFound();
    }
}
