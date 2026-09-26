<?php

namespace Tests\Feature;

use App\Models\AppVersion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_download_is_served_as_an_android_package_and_never_as_a_zip(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('apk/vati-1.3.0.apk', 'fake-apk');

        $version = AppVersion::create([
            'version_code' => '13',
            'version_name' => '1.3.0.zip',
            'file_path' => 'apk/vati-1.3.0.apk',
            'file_name' => 'vati-1.3.0.apk',
            'file_size' => 8,
            'is_latest' => true,
            'is_active' => true,
            'uploaded_by' => User::factory()->create()->id,
        ]);

        $this->assertSame('VATI-1.3.0.apk', $version->downloadFileName());

        $response = $this->get('/api/v1/app/'.$version->id.'/download')->assertOk();

        $this->assertStringContainsString('VATI-1.3.0.apk', (string) $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('.zip', (string) $response->headers->get('content-disposition'));
        $this->assertSame('application/vnd.android.package-archive', $response->headers->get('content-type'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
    }

    public function test_admin_upload_rejects_a_zip_archive(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->post('/admin/system/app-versions', [
                'apk' => UploadedFile::fake()->create('vati-1.4.0.zip', 12, 'application/zip'),
                'version_code' => '14',
                'version_name' => '1.4.0',
            ])
            ->assertSessionHasErrors('apk');

        $this->assertDatabaseCount('app_versions', 0);
    }
}
