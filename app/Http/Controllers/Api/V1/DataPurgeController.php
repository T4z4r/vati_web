<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DataPurgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataPurgeController extends Controller
{
    public function __construct(
        private DataPurgeService $purgeService
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->purgeService->summary(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'entity' => 'required|in:members,groups,applications,loans,loan_products',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $preview = $this->purgeService->preview(
            $request->entity,
            $request->input('from'),
            $request->input('to'),
            $request->integer('branch_id')
        );

        $validation = $this->purgeService->validate(
            $request->entity,
            $request->input('from'),
            $request->input('to'),
            $request->integer('branch_id')
        );

        return response()->json([
            'success' => true,
            'data' => array_merge($preview, $validation),
        ]);
    }

    public function purge(Request $request): JsonResponse
    {
        $this->authorize('purge-system-data');

        $request->validate([
            'entity' => 'required|in:members,groups,applications,loans,loan_products',
            'confirmation_phrase' => 'required|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        try {
            $result = $this->purgeService->purge(
                $request->entity,
                $request->input('from'),
                $request->input('to'),
                $request->integer('branch_id'),
                $request->confirmation_phrase
            );

            activity()
                ->causedBy($request->user())
                ->withProperties([
                    'entity' => $request->entity,
                    'count' => $result['deleted'],
                    'cascade' => $result['cascade'] ?? [],
                    'filters' => $request->only(['from', 'to', 'branch_id']),
                ])
                ->log("Data purge executed: {$request->entity} ({$result['deleted']} records)");

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
