<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AppVersionController extends Controller
{
    public function index()
    {
        $versions = AppVersion::with('uploader')->latest()->paginate(20);
        return view('admin.system.app-versions', compact('versions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'apk' => ['required', 'file', 'mimes:apk', 'max:102400'],
            'version_code' => ['required', 'string', 'max:20'],
            'version_name' => ['required', 'string', 'max:50'],
            'release_notes' => ['nullable', 'string', 'max:2000'],
            'is_latest' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('apk');
        $fileName = 'vati-' . $data['version_name'] . '-' . time() . '.apk';
        $path = $file->storeAs('apk', $fileName, 'public');

        if (!empty($data['is_latest'])) {
            AppVersion::query()->where('is_latest', true)->update(['is_latest' => false]);
        }

        $version = AppVersion::create([
            'version_code' => $data['version_code'],
            'version_name' => $data['version_name'],
            'file_path' => $path,
            'file_name' => $fileName,
            'file_size' => $file->getSize(),
            'release_notes' => $data['release_notes'] ?? null,
            'is_latest' => !empty($data['is_latest']),
            'is_active' => true,
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.system.app-versions')->with('success', 'APK uploaded successfully. Version ' . $version->version_name);
    }

    public function destroy(AppVersion $appVersion)
    {
        Storage::disk('public')->delete($appVersion->file_path);
        $appVersion->delete();

        return redirect()->route('admin.system.app-versions')->with('success', 'Version deleted.');
    }

    public function download(AppVersion $appVersion)
    {
        abort_unless(Storage::disk('public')->exists($appVersion->file_path), 404);

        return Storage::disk('public')->download(
            $appVersion->file_path,
            'VATI-' . $appVersion->version_name . '.apk'
        );
    }

    public function toggleLatest(AppVersion $appVersion)
    {
        AppVersion::query()->where('is_latest', true)->update(['is_latest' => false]);
        $appVersion->update(['is_latest' => true]);

        return redirect()->route('admin.system.app-versions')->with('success', 'Latest version set to ' . $appVersion->version_name);
    }
}
