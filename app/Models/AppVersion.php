<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_latest' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeLatest($query)
    {
        return $query->where('is_latest', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function downloadFileName(): string
    {
        $version = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $this->version_name) ?: 'app';
        $version = trim((string) preg_replace('/\.(zip|apk|aab|xapk)$/i', '', $version), '-.');

        return 'VATI-'.($version !== '' ? $version : 'app').'.apk';
    }

    public function formattedFileSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
