<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Constants\Modules;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends BaseApiController
{
    public function query(Request $request, AssistantService $assistant): JsonResponse
    {
        if ($error = $this->requireModuleAccess(Modules::MEDICATIONS)) {
            return $error;
        }

        if ($error = $this->requirePermission('view_medications')) {
            return $error;
        }

        $validated = $request->validate([
            'message' => 'required|string|max:500',
            'context_resident_id' => 'nullable|integer',
        ]);

        $result = $assistant->handle(
            $validated['message'],
            $request->user(),
            $validated['context_resident_id'] ?? null,
        );

        return $this->success($result);
    }

    public function suggestions(Request $request, AssistantService $assistant): JsonResponse
    {
        if ($error = $this->requireModuleAccess(Modules::MEDICATIONS)) {
            return $error;
        }

        if ($error = $this->requirePermission('view_medications')) {
            return $error;
        }

        return $this->success([
            'suggestions' => $assistant->starterSuggestions($request->user()),
        ]);
    }
}
