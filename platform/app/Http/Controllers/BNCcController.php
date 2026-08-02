<?php

namespace App\Http\Controllers;

use App\Models\BNCcComponentSchema;
use App\Models\BNCcNode;
use Illuminate\Http\Request;

class BNCcController extends Controller
{
    /**
     * Retorna o schema estrutural de navegação para a disciplina e etapa.
     */
    public function schema(Request $request)
    {
        $request->validate([
            'discipline_id' => 'required|integer|exists:disciplines,id',
            'stage' => 'required|string',
        ]);

        $schema = BNCcComponentSchema::where('discipline_id', $request->discipline_id)
            ->where('stage', $request->stage)
            ->first();

        return response()->json([
            'schema' => $schema ? $schema->schema_json : [],
        ]);
    }

    /**
     * Retorna nós da BNCC com base nos filtros passados (para navegação em cascata).
     */
    public function nodes(Request $request)
    {
        $request->validate([
            'discipline_id' => 'required|integer',
            'stage' => 'required|string',
            'type' => 'required|string',
            'parent_id' => 'nullable|integer',
            'grade' => 'nullable|string',
        ]);

        $query = BNCcNode::where('discipline_id', $request->discipline_id)
            ->where('stage', $request->stage)
            ->where('type', $request->type)
            ->where('is_active', true);

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } else {
            $query->whereNull('parent_id');
        }

        // Se passar série (grade), filtra nodes de nivel que dependem disso (como obj e skill)
        // Mas se o nodo atender a varias series ou for nivel abrangente, pode ser nulo.
        if ($request->filled('grade')) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('grade')->orWhere('grade', $request->grade);
            });
        }

        return response()->json([
            'nodes' => $query->get(['id', 'title', 'code', 'type', 'grade', 'parent_id']),
        ]);
    }

    /**
     * Busca habilidades ativas por código ou trecho do texto.
     */
    public function search(Request $request)
    {
        $request->validate([
            'discipline_id' => 'nullable|integer',
            'stage' => 'nullable|string',
            'grade' => 'nullable|string',
            'q' => 'required|string|min:2',
        ]);

        $query = BNCcNode::where('type', 'skill')->where('is_active', true);

        // A busca via Autocomplete é solta (global) se o usuário preencher 'q'
        // Dessa forma ele consegue buscar "Matemática" mesmo se a etapa no select estiver Iniciais
        $term = '%'.$request->q.'%';
        $query->where(function ($q) use ($term) {
            $q->where('code', 'like', $term)
                ->orWhere('title', 'like', $term);
        });

        return response()->json([
            'skills' => $query->take(20)->get(['id', 'code', 'title', 'grade', 'stage']),
        ]);
    }
}
