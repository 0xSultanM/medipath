<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Persists theme / locale / timezone preferences (G7: System→user override). */
class PreferenceController extends Controller
{
    public function theme(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(['system', 'light', 'dark'])],
        ]);

        UserPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['theme' => $data['theme']],
        );

        return response()->json(['theme' => $data['theme']]);
    }
}
