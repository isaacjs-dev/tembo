<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrTemplate;
use App\Models\OmrTemplateQuestion;
use App\Models\OmrTemplateVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OmrTemplateController extends Controller
{
    public function index()
    {
        // Visibilidade: do sistema + da(s) org(s) do usuário + de sua propriedade.
        $templates = OmrTemplate::visible()
            ->withCount('questions')
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('omr.templates.index', compact('templates'));
    }

    public function create()
    {
        return view('omr.templates.editor', ['template' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validateTemplate($request);
        $user = auth()->user();
        $layout = $this->buildLayoutConfig($request);
        $header = $this->buildHeaderConfig($request);
        $logoPath = $this->saveLogo($request);

        $template = OmrTemplate::create(array_merge(
            $this->commonColumns($layout),
            [
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::random(5),
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'owner_type' => User::class,
                'owner_id' => $user->id,
                'visibility_scope' => $request->input('visibility_scope', 'org_public'),
                'is_system' => false,
                'is_default' => false,
                'is_active' => true,
                'layout_config' => $layout,
                'header_config' => $header,
                'logo_path' => $logoPath,
                'current_version' => 1,
            ]
        ));

        OmrTemplateVersion::create([
            'omr_template_id' => $template->id,
            'version' => 1,
            'layout_config' => $layout,
            'header_config' => $header,
            'logo_path' => $logoPath,
        ]);

        return redirect()->route('institution.omr.templates.edit', $template->id)
            ->with('status', 'Template criado com sucesso.');
    }

    public function edit(OmrTemplate $template)
    {
        $this->authorizeEdit($template);

        return view('omr.templates.editor', compact('template'));
    }

    public function update(Request $request, OmrTemplate $template)
    {
        $this->authorizeEdit($template);
        $data = $this->validateTemplate($request);
        $layout = $this->buildLayoutConfig($request);
        $header = $this->buildHeaderConfig($request);
        $logoPath = $this->saveLogo($request) ?? $template->logo_path;

        // Nova versão a cada edição → provas antigas continuam lendo com a versão delas.
        $newVersion = (int) ($template->current_version ?? 1) + 1;

        $template->update(array_merge(
            $this->commonColumns($layout),
            [
                'name' => $data['name'],
                'visibility_scope' => $request->input('visibility_scope', $template->visibility_scope),
                'layout_config' => $layout,
                'header_config' => $header,
                'logo_path' => $logoPath,
                'current_version' => $newVersion,
            ]
        ));

        OmrTemplateVersion::create([
            'omr_template_id' => $template->id,
            'version' => $newVersion,
            'layout_config' => $layout,
            'header_config' => $header,
            'logo_path' => $logoPath,
        ]);

        return redirect()->route('institution.omr.templates.edit', $template->id)
            ->with('status', "Template atualizado (versão {$newVersion}).");
    }

    public function destroy(OmrTemplate $template)
    {
        $this->authorizeEdit($template);
        if ($template->is_default || $template->is_system) {
            return back()->withErrors('O template padrão do sistema não pode ser excluído.');
        }
        $hasHistoricalUse = ExamCopy::query()->where('card_template_id', $template->id)->exists()
            || Exam::withoutGlobalScopes()->where('card_template_id', $template->id)->exists()
            || $template->scans()->exists();
        if ($hasHistoricalUse) {
            return back()->withErrors(
                'Este template já foi usado em uma cópia histórica e não pode ser excluído. Desative-o para impedir novos usos.'
            );
        }
        $template->delete();

        return redirect()->route('institution.omr.templates.index')
            ->with('status', 'Template excluído.');
    }

    /* ───────────────────────── Helpers ───────────────────────── */

    /** Só o owner, a org do usuário ou um global_admin podem editar; sistema é bloqueado. */
    private function authorizeEdit(OmrTemplate $template): void
    {
        $user = auth()->user();
        abort_if($template->is_system, 403);
        if ($user && $user->type === 'global_admin') {
            return;
        }
        abort_if($template->is_system, 403, 'Template do sistema é somente leitura.');

        $sameWorkspace = (int) $template->organization_id === (int) $user?->organization_id;
        $ownsTemplate = (int) $template->created_by === (int) $user?->id
            || ($template->owner_type === User::class && (int) $template->owner_id === (int) $user?->id);
        $workspaceRole = request()->attributes->get('workspace_role');
        $managesWorkspace = in_array($workspaceRole, ['admin', 'institution_admin'], true);
        $visible = $sameWorkspace && ($ownsTemplate || $managesWorkspace);
        abort_unless($visible, 403, 'Sem permissão para editar este template.');
    }

    private function authorizeView(OmrTemplate $template): void
    {
        abort_unless(
            OmrTemplate::visible(auth()->user())->whereKey($template->id)->exists(),
            403,
        );
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'visibility_scope' => 'nullable|in:private,org_public',
            'max_questions' => 'required|integer|min:1|max:300',
            'max_columns' => 'required|integer|min:1|max:6',
            'columns' => 'required|integer|min:1|max:6',
            'rows_per_column' => 'required|integer|min:1|max:60',
            'max_options' => 'required|integer|min:2|max:6',
            'bubble_diameter_mm' => 'required|numeric|min:3|max:10',
            'fiducial_size_mm' => 'required|numeric|min:4|max:15',
            'margins_mm' => 'nullable|numeric|min:5|max:25',
            'frame_left_mm' => 'required|numeric|min:5|max:120',
            'frame_top_mm' => 'required|numeric|min:20|max:160',
            'frame_width_mm' => 'required|numeric|min:80|max:200',
            'row_spacing_mm' => 'required|numeric|min:4|max:20',
            'cell_indent_mm' => 'required|numeric|min:4|max:60',
            'grid_pad_top_mm' => 'nullable|numeric|min:0|max:30',
            'option_gap_mm' => 'nullable|numeric|min:0|max:10',
            'header_title' => 'nullable|string|max:120',
            'logo' => 'nullable|image|max:2048',
        ]);
    }

    /** Monta o layout_config consumido por AnswerSheetGeneratorService::buildPageGeometry. */
    private function buildLayoutConfig(Request $r): array
    {
        return [
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'margins_mm' => (float) $r->input('margins_mm', 12),
            'max_questions' => (int) $r->input('max_questions', 40),
            'columns' => (int) $r->input('columns', 2),
            'rows_per_column' => (int) $r->input('rows_per_column', 20),
            'max_options' => (int) $r->input('max_options', 5),
            'bubble_diameter_mm' => (float) $r->input('bubble_diameter_mm', 5.5),
            'frame_fiducial_mm' => (float) $r->input('fiducial_size_mm', 8.0),
            'frame_left_mm' => (float) $r->input('frame_left_mm', 12.0),
            'frame_top_mm' => (float) $r->input('frame_top_mm', 56.0),
            'frame_width_mm' => (float) $r->input('frame_width_mm', 186.0),
            'row_spacing_mm' => (float) $r->input('row_spacing_mm', 9.0),
            'cell_indent_mm' => (float) $r->input('cell_indent_mm', 14.0),
            'grid_pad_top_mm' => (float) $r->input('grid_pad_top_mm', 8.0),
            'option_gap_mm' => (float) $r->input('option_gap_mm', 2.0),
            'qr_position' => $r->input('qr_position', 'top_right'),
        ];
    }

    private function buildHeaderConfig(Request $r): array
    {
        return [
            'title' => $r->input('header_title', 'CARTÃO RESPOSTA'),
            'show_institution' => true,
            'show_qr' => true,
        ];
    }

    /** Colunas "planas" do modelo (espelham o layout_config) + campos legados NOT NULL. */
    private function commonColumns(array $layout): array
    {
        return [
            'max_questions' => (int) $layout['max_questions'],
            'max_columns' => (int) request()->input('max_columns', $layout['columns']),
            'columns' => (int) $layout['columns'],
            'rows_per_column' => (int) $layout['rows_per_column'],
            'max_options' => (int) $layout['max_options'],
            'total_questions' => (int) $layout['max_questions'],
            'total_pages' => 1,
            'width' => 2480,
            'height' => 3508,
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'corner_points_json' => ['TL' => [0, 0], 'TR' => [0, 0], 'BR' => [0, 0], 'BL' => [0, 0]],
            'thresholds_json' => ['mark' => 0.45, 'blank' => 0.15, 'uncertain_low' => 0.25, 'uncertain_high' => 0.40],
        ];
    }

    private function saveLogo(Request $request): ?string
    {
        if ($request->hasFile('logo')) {
            return $request->file('logo')->store('omr-logos', 'public');
        }

        return null;
    }

    /* ───────────────────── Legado (mantido) ───────────────────── */

    public function exportJson(OmrTemplate $template)
    {
        $this->authorizeView($template);
        $template->load('questions');

        return response()->json($template->toEngineJson());
    }

    public function generateRois(Request $request, OmrTemplate $template)
    {
        $this->authorizeEdit($template);
        $request->validate([
            'grid_start_x' => 'required|numeric',
            'grid_start_y' => 'required|numeric',
            'col_spacing' => 'required|numeric',
            'row_spacing' => 'required|numeric',
            'bubble_w' => 'required|numeric|min:5',
            'bubble_h' => 'required|numeric|min:5',
            'option_spacing' => 'required|numeric',
            'options_per_question' => 'nullable|array',
        ]);

        $cols = $template->columns;
        $rowsPerCol = $template->rows_per_column;
        $maxOpts = $template->max_options;
        $totalQ = $template->total_questions;

        $startX = (float) $request->grid_start_x;
        $startY = (float) $request->grid_start_y;
        $colSpacing = (float) $request->col_spacing;
        $rowSpacing = (float) $request->row_spacing;
        $bw = (float) $request->bubble_w;
        $bh = (float) $request->bubble_h;
        $optSpacing = (float) $request->option_spacing;

        $template->questions()->delete();

        $qNum = 1;
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

        for ($col = 0; $col < $cols && $qNum <= $totalQ; $col++) {
            for ($row = 0; $row < $rowsPerCol && $qNum <= $totalQ; $row++) {
                $numOpts = $request->options_per_question[$qNum] ?? $maxOpts;
                $optionLabels = array_slice($letters, 0, $numOpts);

                $rois = [];
                $baseX = $startX + ($col * $colSpacing);
                $baseY = $startY + ($row * $rowSpacing);

                foreach ($optionLabels as $i => $label) {
                    $rois[$label] = [
                        'x' => round($baseX + ($i * $optSpacing), 1),
                        'y' => round($baseY, 1),
                        'w' => round($bw, 1),
                        'h' => round($bh, 1),
                    ];
                }

                OmrTemplateQuestion::create([
                    'omr_template_id' => $template->id,
                    'question_number' => $qNum,
                    'option_labels_json' => $optionLabels,
                    'rois_json' => $rois,
                ]);

                $qNum++;
            }
        }

        return redirect()->route('institution.omr.templates.edit', $template->id)
            ->with('status', ($qNum - 1).' questões/ROIs geradas automaticamente.');
    }
}
