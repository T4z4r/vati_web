<?php
use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;
uses(TestCase::class, CreatesApplication::class);

it('deletes a member document over HTTP', function () {
    $this->app = require __DIR__.'/../../bootstrap/app.php';
    $user = User::first() ?? User::factory()->create();
    $member = Member::factory()->create();
    $doc = $member->documents()->create(['document_type'=>'national_id','file_name'=>'n.png','file_path'=>'members/x.png','mime_type'=>'image/png','file_size'=>10]);
    $this->actingAs($user,'sanctum')->deleteJson("/api/v1/members/{$member->id}/documents/{$doc->id}")->assertNoContent();
    $this->assertDatabaseMissing('member_documents', ['id'=>$doc->id]);
    echo "DELETE_OK_204";
});
