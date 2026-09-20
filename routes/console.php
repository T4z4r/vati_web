<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('signatures:cleanup', function () {
    $service = app(\App\Services\MemberSignatureService::class);
    \App\Models\MemberDocument::onlyTrashed()->where('file_cleanup_pending', true)
        ->each(fn ($document) => $service->cleanup($document));
    $disk = \Illuminate\Support\Facades\Storage::disk('signatures');
    foreach ($disk->allFiles('members') as $path) {
        if ($disk->lastModified($path) < now()->subDay()->timestamp
            && ! \App\Models\MemberDocument::withTrashed()->where('disk', 'signatures')->where('file_path', $path)->exists()) {
            $disk->delete($path);
        }
    }
})->purpose('Retry signature deletion and remove orphaned signature files older than one day');

\Illuminate\Support\Facades\Schedule::command('signatures:cleanup')->hourly()->withoutOverlapping();
