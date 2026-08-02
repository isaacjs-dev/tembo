<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\KnowledgeArea;
use Illuminate\Http\Request;

class TaxonomyController extends Controller
{
    /**
     * Store a newly created Knowledge Area in storage via API/AJAX.
     */
    public function storeKnowledgeArea(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $area = KnowledgeArea::firstOrCreate([
            'name' => $validated['name'],
            'organization_id' => auth()->user()->organization_id,
        ]);

        return response()->json([
            'success' => true,
            'id' => $area->id,
            'name' => $area->name,
            'message' => 'Área de conhecimento criada com sucesso!',
        ]);
    }

    /**
     * Store a newly created Discipline in storage via API/AJAX.
     */
    public function storeDiscipline(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $discipline = Discipline::firstOrCreate([
            'name' => $validated['name'],
            'organization_id' => auth()->user()->organization_id,
        ]);

        return response()->json([
            'success' => true,
            'id' => $discipline->id,
            'name' => $discipline->name,
            'message' => 'Disciplina criada com sucesso!',
        ]);
    }
}
