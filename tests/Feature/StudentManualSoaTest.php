<?php

namespace Tests\Feature;

use App\Models\StudentManualSoa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentManualSoaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_student_manual_soa_can_be_created_and_queried(): void
    {
        $file = UploadedFile::fake()->create('ahmad_august_2026_soa.pdf', 250, 'application/pdf');
        $path = $file->store('soa/manual/AMIS-2026-DEMO-01', 'local');

        $soa = StudentManualSoa::create([
            'student_identifier' => 'AMIS-2026-DEMO-01',
            'student_name' => 'AHMAD Z. LINGASA',
            'family_email' => 'test.family@example.com',
            'grade_level' => 'Grade 1',
            'school_year' => '2026-2027',
            'billing_month' => 'AUGUST 2026',
            'version' => 1,
            'is_current' => true,
            'file_path' => $path,
            'original_filename' => 'ahmad_august_2026_soa.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $file->getSize(),
            'uploaded_by' => 'Finance Staff',
            'remarks' => 'Official August SOA with siblings discount.',
        ]);

        $this->assertDatabaseHas('student_manual_soas', [
            'id' => $soa->id,
            'student_identifier' => 'AMIS-2026-DEMO-01',
            'billing_month' => 'AUGUST 2026',
            'is_current' => true,
        ]);

        $this->assertTrue($soa->is_pdf);
        $this->assertFalse($soa->is_image);
        $this->assertEquals('250.0 KB', $soa->formatted_file_size);

        $currentSoas = StudentManualSoa::forStudent('AMIS-2026-DEMO-01')->current()->get();
        $this->assertTrue($currentSoas->contains('id', $soa->id));
    }

    public function test_parent_cannot_view_unauthorized_student_soa(): void
    {
        $file = UploadedFile::fake()->create('other_student_soa.pdf', 100, 'application/pdf');
        $path = $file->store('soa/manual/OTHER-STUDENT', 'local');

        $soa = StudentManualSoa::create([
            'student_identifier' => 'OTHER-STUDENT',
            'student_name' => 'OTHER STUDENT',
            'family_email' => 'other.family@example.com',
            'school_year' => '2026-2027',
            'billing_month' => 'AUGUST 2026',
            'version' => 1,
            'is_current' => true,
            'file_path' => $path,
            'original_filename' => 'other_student_soa.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 102400,
        ]);

        $unauthorizedParent = User::factory()->create([
            'email' => 'unauthorized.parent.' . uniqid() . '@example.com',
            'role' => 'parent',
        ]);

        $response = $this->actingAs($unauthorizedParent)->get(route('payment.manual-soa.view', $soa));
        $response->assertStatus(403);
    }

    public function test_authorized_parent_can_view_and_download_own_student_soa(): void
    {
        $parent = User::query()->where('email', 'zhairel.lingasa@gmail.com')->first()
            ?? User::factory()->create(['role' => 'parent']);

        $file = UploadedFile::fake()->create('ahmad_august_2026_soa.pdf', 100, 'application/pdf');
        $path = $file->store('soa/manual/AMIS-2026-DEMO-01', 'local');

        $soa = StudentManualSoa::create([
            'student_identifier' => 'AMIS-2026-DEMO-01',
            'student_name' => 'AHMAD Z. LINGASA',
            'family_email' => $parent->email,
            'school_year' => '2026-2027',
            'billing_month' => 'AUGUST 2026',
            'version' => 1,
            'is_current' => true,
            'file_path' => $path,
            'original_filename' => 'ahmad_august_2026_soa.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 102400,
        ]);

        $viewResponse = $this->actingAs($parent)->get(route('payment.manual-soa.view', $soa));
        $viewResponse->assertStatus(200);

        $downloadResponse = $this->actingAs($parent)->get(route('payment.manual-soa.download', $soa));
        $downloadResponse->assertStatus(200);
    }
}
