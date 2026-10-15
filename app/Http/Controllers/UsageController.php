<?php
namespace App\Http\Controllers;
use App\Actions\Usage\RecordUsageAction;
use App\Http\Requests\StoreUsageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class UsageController extends Controller
{
    public function store(StoreUsageRequest $request, RecordUsageAction $action): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (!$key) return response()->json(['message' => 'Idempotency-Key header is required.'], 422);
        try {
            $event = $action->execute($request->validated(), $key);
            return response()->json(['id' => $event->id, 'units' => $event->units, 'occurred_at' => $event->occurred_at], 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
