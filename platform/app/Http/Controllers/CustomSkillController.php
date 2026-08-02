<?php

namespace App\Http\Controllers;

use App\Models\CustomSkill;
use Illuminate\Http\Request;

class CustomSkillController extends Controller
{
    /**
     * Busca habilidades personalizadas ativas na organização do usuário.
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $query = CustomSkill::where('organization_id', auth()->user()->organization_id);

        $term = '%'.$request->q.'%';
        $query->where('name', 'like', $term);

        return response()->json([
            'skills' => $query->take(20)->get(['id', 'name']),
        ]);
    }

    /**
     * Cria uma nova habilidade personalizada on-the-fly.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $skill = CustomSkill::firstOrCreate([
            'organization_id' => auth()->user()->organization_id,
            'name' => $validated['name'],
        ]);

        return response()->json([
            'skill' => ['id' => $skill->id, 'name' => $skill->name],
        ]);
    }
}
