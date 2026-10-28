<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrmWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Database is already seeded or we can use seeded users
    }

    public function test_admin_can_login_and_access_dashboard(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin',
            'password' => 'Admin123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ]);

        $token = $response->json('token');
        $this->assertEquals('admin', $response->json('user.username'));
        $this->assertTrue($response->json('user.must_change_password'));

        $metricsResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard/metrics');

        $metricsResponse->assertStatus(200)
            ->assertJsonStructure([
                'summary' => ['total_leads', 'new_today', 'converted_leads', 'due_today'],
                'status_breakdown',
            ]);
    }

    public function test_employee_can_only_see_their_assigned_leads(): void
    {
        $employee = User::where('email', 'ananya@vistaboundintegrated.com')->first();
        $token = $employee->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/leads');

        $response->assertStatus(200);

        $leads = $response->json('data');
        foreach ($leads as $lead) {
            $this->assertEquals($employee->id, $lead['assigned_to']);
        }
    }

    public function test_duplicate_lead_prevention(): void
    {
        $admin = User::where('role', 'admin')->first();
        $token = $admin->createToken('test')->plainTextToken;

        $phone = '+91 98450 12345'; // Already exists in seeded data

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/leads', [
                'name' => 'Duplicate Test',
                'phone' => $phone,
                'email' => 'duplicate@test.com',
                'source' => 'Website',
            ]);

        $response->assertStatus(409)
            ->assertJsonFragment(['duplicate' => true]);
    }

    public function test_lead_creation_and_activity_logging(): void
    {
        $admin = User::where('role', 'admin')->first();
        $token = $admin->createToken('test')->plainTextToken;

        $uniquePhone = '+91 998' . rand(1000000, 9999999);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/leads', [
                'name' => 'Unique Client Bangalore',
                'phone' => $uniquePhone,
                'email' => 'client@uniquebangalore.in',
                'company_name' => 'Unique Bangalore Pvt Ltd',
                'source' => 'Website',
                'service_interest' => 'IT Infrastructure & Hardware AMC',
                'status' => 'new',
                'priority' => 'high',
            ]);

        $response->assertStatus(201);
        $leadId = $response->json('lead.id');

        $lead = Lead::find($leadId);
        $this->assertNotNull($lead);
        $this->assertEquals(Lead::normalizePhone($uniquePhone), $lead->phone_normalized);
        $this->assertStringStartsWith('VB-LD-', $lead->reference_id);

        // Check activity
        $this->assertTrue($lead->activities()->where('activity_type', 'lead_created')->exists());
    }

    public function test_whatsapp_preview_and_log(): void
    {
        $admin = User::where('role', 'admin')->first();
        $token = $admin->createToken('test')->plainTextToken;

        $lead = Lead::first();

        $previewResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/leads/{$lead->id}/whatsapp/preview", [
                'custom_text' => 'Hello {lead_name}, greetings from Vistabound!',
            ]);

        $previewResponse->assertStatus(200)
            ->assertJsonFragment([
                'rendered_message' => "Hello {$lead->name}, greetings from Vistabound!",
            ]);

        $logResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/leads/{$lead->id}/whatsapp/log", [
                'message' => "Hello {$lead->name}, greetings from Vistabound!",
            ]);

        $logResponse->assertStatus(200);
        $this->assertTrue($lead->activities()->where('activity_type', 'whatsapp_sent')->exists());
    }
}
