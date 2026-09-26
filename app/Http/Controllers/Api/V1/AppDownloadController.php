<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AppDownloadController extends ApiController
{
    public function latest()
    {
        $version = $this->latestVersion();

        if (! $version) {
            return response()->json(['success' => true, 'data' => null, 'message' => 'No app version available yet.']);
        }

        return response()->json([
            'success' => true,
            'data' => $this->versionPayload($version),
        ]);
    }

    public function updateCheck(Request $request)
    {
        $data = $request->validate([
            'version_code' => ['nullable', 'integer', 'min:0'],
        ]);

        $version = $this->latestVersion();
        $currentVersionCode = array_key_exists('version_code', $data) ? (int) $data['version_code'] : null;

        if (! $version) {
            return response()->json([
                'success' => true,
                'data' => [
                    'update_available' => false,
                    'current_version_code' => $currentVersionCode,
                    'latest_version' => null,
                ],
                'message' => 'No app version available yet.',
            ]);
        }

        $latestVersionCode = (int) $version->version_code;
        $updateAvailable = $currentVersionCode === null || $latestVersionCode > $currentVersionCode;

        return response()->json([
            'success' => true,
            'data' => [
                'update_available' => $updateAvailable,
                'current_version_code' => $currentVersionCode,
                'latest_version' => $this->versionPayload($version),
            ],
        ]);
    }

    public function download(AppVersion $appVersion)
    {
        abort_unless($appVersion->is_active, 404);

        abort_unless(Storage::disk('public')->exists($appVersion->file_path), 404);

        return Storage::disk('public')->download($appVersion->file_path, $appVersion->downloadFileName(), [
            'Content-Type' => 'application/vnd.android.package-archive',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function latestVersion(): ?AppVersion
    {
        return AppVersion::where('is_active', true)
            ->orderByDesc('is_latest')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    private function versionPayload(AppVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_code' => $version->version_code,
            'version_name' => $version->version_name,
            'release_notes' => $version->release_notes,
            'file_size_bytes' => $version->file_size,
            'file_size' => $version->formattedFileSize(),
            'download_url' => route('api.app.download', $version->id),
            'published_at' => $version->created_at?->toIso8601String(),
        ];
    }
}
