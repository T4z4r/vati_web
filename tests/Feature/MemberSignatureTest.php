<?php

namespace Tests\Feature;

use App\Models\{Area, Branch, Member, MemberDocument, Region, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberSignatureTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('signatures');
        $region = Region::create(['name' => 'Region']);
        $area = Area::create(['region_id' => $region->id, 'name' => 'Area']);
        $branch = Branch::create(['area_id' => $area->id, 'branch_code' => 'SIG', 'branch_name' => 'Signature']);
        $group = \App\Models\MemberGroup::create(['branch_id' => $branch->id, 'group_code' => 'SIG-G', 'group_name' => 'Signature Group']);
        $this->member = Member::create(['branch_id' => $branch->id, 'group_id' => $group->id, 'membership_number' => 'SIG-1', 'first_name' => 'Asha', 'last_name' => 'Musa', 'phone' => '255711111111']);
        Sanctum::actingAs(User::factory()->create());
    }

    private function url(): string
    {
        return '/api/v1/members/'.$this->member->id.'/documents';
    }

    public function test_private_png_upload_retry_conflict_download_and_delete(): void
    {
        $image = UploadedFile::fake()->image('drawing.png', 100, 40);
        $bytes = file_get_contents($image->getRealPath());
        $upload = fn () => new UploadedFile($image->getRealPath(), 'sahihi.png', 'application/octet-stream', null, true);
        $created = $this->postJson($this->url(), ['document_type' => 'signature', 'file' => $upload()])->assertCreated()->assertJsonPath('data.status', 'uploaded');
        $id = $created->json('data.id');
        $doc = MemberDocument::findOrFail($id);
        $this->assertSame(hash('sha256', $bytes), $doc->sha256);
        $this->assertSame(strlen($bytes), $created->json('data.size_bytes'));
        Storage::disk('signatures')->assertExists($doc->file_path);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.0.document_type', 'signature');
        $this->postJson($this->url(), ['document_type' => 'signature', 'file' => $upload()])->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson($this->url(), ['document_type' => 'signature', 'file' => UploadedFile::fake()->image('different.png', 101, 40)])->assertConflict();
        $this->assertCount(1, Storage::disk('signatures')->allFiles());
        $download = $this->getJson($this->url().'/'.$id.'/download')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($bytes, $download->streamedContent());
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $this->deleteJson($this->url().'/'.$id)->assertNoContent();
        Storage::disk('signatures')->assertMissing($doc->file_path);
        $this->assertSoftDeleted('member_documents', ['id' => $id]);
        $this->getJson($this->url())->assertJsonCount(0, 'data');
    }

    public function test_invalid_signature_files_and_pixel_limits_are_rejected(): void
    {
        $this->postJson($this->url(), ['document_type' => 'signature'])->assertUnprocessable();
        foreach (['not an image', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'] as $bytes) {
            $this->postJson($this->url(), ['document_type' => 'signature', 'file' => UploadedFile::fake()->createWithContent('fake.png', $bytes)])->assertUnprocessable();
        }
        config(['signatures.max_pixels' => 10]);
        $this->postJson($this->url(), ['document_type' => 'signature', 'file' => UploadedFile::fake()->image('large.png', 20, 20)])->assertUnprocessable();
        $this->assertCount(0, Storage::disk('signatures')->allFiles());
    }

    public function test_database_failure_cleans_staged_file(): void
    {
        MemberDocument::creating(fn () => throw new \RuntimeException('Simulated insert failure'));
        try {
            $this->postJson($this->url(), ['document_type' => 'signature', 'file' => UploadedFile::fake()->image('signature.png')])->assertServerError();
            $this->assertCount(0, Storage::disk('signatures')->allFiles());
        } finally {
            MemberDocument::flushEventListeners();
        }
    }
}
