<?php

namespace Tests\Feature;

use App\Models\AppVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppUpdateEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_update_check_returns_latest_uploaded_apk_when_client_is_outdated(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('apk/vati-1.2.0.apk', 'fake-apk');

        $uploader = User::factory()->create();
        $version = AppVersion::create([
            'version_code' => '12',
            'version_name' => '1.2.0',
            'file_path' => 'apk/vati-1.2.0.apk',
            'file_name' => 'vati-1.2.0.apk',
            'file_size' => 8,
            'release_notes' => 'Remote update build.',
            'is_latest' => true,
            'is_active' => true,
            'uploaded_by' => $uploader->id,
        ]);

        $this->getJson('/api/v1/app/update-check?version_code=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.current_version_code', 10)
            ->assertJsonPath('data.latest_version.id', $version->id)
            ->assertJsonPath('data.latest_version.version_code', '12')
            ->assertJsonPath('data.latest_version.download_url', route('api.app.download', $version));

        $this->getJson('/api/v1/app/update-check?version_code=12')
            ->assertOk()
            ->assertJsonPath('data.update_available', false);

        $download = $this->get('/api/v1/app/'.$version->id.'/download')->assertOk();
        $this->assertSame('fake-apk', $download->streamedContent());
    }

    public function test_public_update_check_handles_missing_app_version(): void
    {
        $this->getJson('/api/v1/app/update-check?version_code=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.update_available', false)
            ->assertJsonPath('data.latest_version', null);
    }
}
