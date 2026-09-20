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
        config(['signatures.max_pixels' => 4000000]);
        $this->postJson($this->url(), ['document_type' => 'signature', 'file' => UploadedFile::fake()->image('large.png')->size(2049)])->assertUnprocessable();
        $png = UploadedFile::fake()->image('valid.png', 30, 30);
        $corrupt = substr(file_get_contents($png->getRealPath()), 0, 40);
        $this->postJson($this->url(), ['document_type' => 'signature', 'file' => UploadedFile::fake()->createWithContent('corrupt.png', $corrupt)])->assertUnprocessable();
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

    public function test_signature_used_by_witness_cannot_be_deleted_or_downloaded_under_another_member(): void
    {
        $response = $this->postJson($this->url(), ['document_type' => 'signature', 'file' => UploadedFile::fake()->image('signature.png')])->assertCreated();
        $document = MemberDocument::findOrFail($response->json('data.id'));
        $other = $this->member->replicate();
        $other->membership_number = 'SIG-2';
        $other->phone = '255711111113';
        $other->save();
        $this->getJson('/api/v1/members/'.$other->id.'/documents/'.$document->id.'/download')->assertNotFound();
        $product = \App\Models\LoanProduct::create(['name' => 'Signature Loan', 'code' => 'SIG', 'minimum_amount' => 1, 'maximum_amount' => 10000, 'minimum_duration_months' => 1, 'maximum_duration_months' => 12]);
        $application = \App\Models\LoanApplication::create(['application_number' => 'SIG-A', 'member_id' => $other->id, 'group_id' => $other->group_id, 'branch_id' => $other->branch_id, 'loan_product_id' => $product->id, 'requested_amount' => 1000, 'duration_months' => 6, 'status' => 'submitted']);
        \App\Models\GroupMembership::create(['member_id' => $this->member->id, 'group_id' => $this->member->group_id, 'status' => 'active', 'joined_at' => today()]);
        $witnessUrl = '/api/v1/loan-applications/'.$application->id.'/group-witnesses';
        $this->postJson($witnessUrl, ['member_id' => $this->member->id, 'signature_path' => '../../other.png'])->assertUnprocessable();
        $this->postJson($witnessUrl, ['member_id' => $this->member->id, 'signature_path' => $document->file_path])->assertCreated()->assertJsonPath('data.signature_document_id', $document->id);
        $this->deleteJson($this->url().'/'.$document->id, ['force' => true])->assertConflict();
        Storage::disk('signatures')->assertExists($document->file_path);
        $this->assertFalse($document->fresh()->trashed());
    }

    public function test_non_signature_documents_keep_existing_upload_rules(): void
    {
        Storage::fake('public');
        $this->postJson($this->url(), ['document_type' => 'signature_card', 'file' => UploadedFile::fake()->image('card.jpg')])->assertCreated();
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->assertCount(0, Storage::disk('signatures')->allFiles());
    }

    public function test_cleanup_retries_pending_deletion_and_removes_only_stale_orphans(): void
    {
        $response = $this->postJson($this->url(), ['document_type' => 'signature', 'file' => UploadedFile::fake()->image('signature.png')])->assertCreated();
        $document = MemberDocument::findOrFail($response->json('data.id'));
        $document->update(['file_cleanup_pending' => true, 'active_signature_member_id' => null]);
        $document->delete();
        $disk = Storage::disk('signatures');
        $disk->put('members/stale.png', 'orphan');
        touch($disk->path('members/stale.png'), now()->subDays(2)->timestamp);
        $disk->put('members/recent.png', 'in flight');
        $this->artisan('signatures:cleanup')->assertSuccessful();
        $disk->assertMissing($document->file_path);
        $disk->assertMissing('members/stale.png');
        $disk->assertExists('members/recent.png');
        $this->assertFalse((bool) MemberDocument::withTrashed()->find($document->id)->file_cleanup_pending);
    }
}
