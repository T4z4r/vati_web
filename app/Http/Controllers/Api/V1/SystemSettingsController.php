<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SystemSetting::allGrouped(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:5000',
        ]);

        SystemSetting::setMany($request->settings);
        activity()->causedBy($request->user())->withProperties(['updated_keys' => array_keys($request->settings)])->log('System settings updated');

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
            'data' => SystemSetting::allGrouped(),
        ]);
    }
}
