<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('system_settings', 3600, function () {
            return static::pluck('value', 'key')->toArray();
        });

        $value = $settings[$key] ?? $default;

        $type = static::where('key', $key)->value('type') ?? 'string';

        return match ($type) {
            'number' => is_numeric($value) ? (float) $value : $default,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => (string) $value,
        };
    }

    public static function set(string $key, mixed $value): void
    {
        $record = static::where('key', $key)->first();

        if ($record) {
            $record->update(['value' => $value]);
        } else {
            static::create(['key' => $key, 'value' => $value]);
        }

        Cache::forget('system_settings');
    }

    public static function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            static::set($key, $value);
        }
    }

    public static function allGrouped(): array
    {
        return static::orderBy('group')->orderBy('key')->get()->groupBy('group')->map(function ($items) {
            return $items->map(fn ($item) => [
                'key' => $item->key,
                'value' => $item->value,
                'type' => $item->type,
                'description' => $item->description,
            ])->toArray();
        })->toArray();
    }
}
