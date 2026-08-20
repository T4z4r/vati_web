<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AppVersion;
use Illuminate\Http\Request;

class AppDownloadController extends ApiController
{
    public function latest()
    {
        $version = AppVersion::where('is_latest', true)
            ->where('is_active', true)
            ->first();

        if (! $version) {
            return response()->json(['success' => true, 'data' => null, 'message' => 'No app version available yet.']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'version_code' => $version->version_code,
                'version_name' => $version->version_name,
                'release_notes' => $version->release_notes,
                'file_size' => $version->formattedFileSize(),
                'download_url' => route('api.app.download', $version->id),
            ],
        ]);
    }

    public function download(AppVersion $appVersion)
    {
        abort_unless($appVersion->is_active, 404);

        $path = storage_path('app/public/' . $appVersion->file_path);
        abort_unless(file_exists($path), 404);

        return response()->download($path, 'VATI-' . $appVersion->version_name . '.apk');
    }
}
