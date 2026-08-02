<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OMRController extends Controller
{
    public function processScan(Request $request)
    {
        if (! auth()->user()->organization->hasFeature('omr')) {
            return response()->json(['error' => 'Recurso OMR não disponível no seu plano atual.'], 403);
        }

        // Stub implementation
        return response()->json(['status' => 'success', 'message' => 'OMR processed.']);
    }
}
