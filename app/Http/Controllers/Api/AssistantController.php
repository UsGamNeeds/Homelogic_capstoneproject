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
        ]);

        $result = $assistant->handle($validated['message'], $request->user());

        return $this->success($result);
    }
}
