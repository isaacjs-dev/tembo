<?php

namespace App\Http\Controllers;

use App\Models\AppearanceTemplate;
use App\Models\AppearanceTemplateVersion;
use App\Models\TemplateDefault;
use App\Services\AppearanceAssetService;
use App\Services\AppearanceTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class AppearanceTemplateController extends Controller
{
    public function __construct(
        private readonly AppearanceTemplateService $templates,
        private readonly AppearanceAssetService $assets,
    ) {}

    public function index(Request $request)
    {
        $actor = $request->user();
        $organization = $actor->organization;
        abort_unless($organization, 403);
        $catalog = $this->templates->catalogFor($actor, $organization);
        $defaults = TemplateDefault::query()
            ->whereIn('scope_key', ['user:'.$actor->id, 'organization:'.$organization->id])
            ->where('template_type', AppearanceTemplate::class)
            ->get()->keyBy(fn (TemplateDefault $default): string => $default->scope_key.':'.$default->kind);

        return view('appearance-templates.index', compact('catalog', 'defaults', 'organization'));
    }

    public function create(Request $request)
    {
        $kind = $request->string('kind')->toString();
        abort_unless(in_array($kind, ['assessment_layout', 'assessment_header'], true), 404);

        return view('appearance-templates.create', [
            'kind' => $kind,
            'definition' => $this->initialDefinition($kind),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'kind' => ['required', 'in:assessment_layout,assessment_header'],
            'ownership' => ['required', 'in:personal,organization'],
            'definition' => ['required', 'string', 'max:50000'],
        ]);
        $template = $this->templates->create(
            $request->user(),
            $data['kind'],
            $data['name'],
            $this->definition($data['definition']),
            $data['ownership'],
        );

        return redirect()->route('appearance-templates.edit', $template)->with('success', 'Template criado. Agora você pode personalizá-lo.');
    }

    public function duplicate(Request $request, AppearanceTemplate $appearanceTemplate)
    {
        $copy = $this->templates->duplicate($appearanceTemplate, $request->user(), $request->user()->organization);

        return redirect()->route('appearance-templates.edit', $copy)->with('success', 'Cópia criada sem alterar o modelo original.');
    }

    public function edit(Request $request, AppearanceTemplate $appearanceTemplate)
    {
        $this->templates->authorizeMutation($appearanceTemplate, $request->user());
        abort_if($appearanceTemplate->archived_at, 409, 'Template arquivado é somente leitura.');
        $appearanceTemplate->load(['currentVersion', 'versions']);

        return view('appearance-templates.edit', [
            'template' => $appearanceTemplate,
            'definition' => $this->editorDefinition($appearanceTemplate->kind, $appearanceTemplate->currentVersion->definition),
        ]);
    }

    public function update(Request $request, AppearanceTemplate $appearanceTemplate)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'base_version' => ['required', 'integer', 'min:1'],
            'definition' => ['required', 'string', 'max:50000'],
            'summary' => ['nullable', 'string', 'max:500'],
            'asset' => ['nullable', 'file', 'max:2048'],
            'asset_key' => ['nullable', 'regex:/^[a-z][a-z0-9_-]{0,39}$/'],
        ]);
        $appearanceTemplate->load('currentVersion');
        $versionAssets = $appearanceTemplate->currentVersion->assets ?? [];
        $newAsset = null;
        if ($request->hasFile('asset')) {
            $newAsset = $this->assets->store($request->file('asset'));
            $versionAssets[$data['asset_key'] ?? 'logo'] = $newAsset;
        }

        try {
            DB::transaction(function () use ($appearanceTemplate, $request, $data, $versionAssets): void {
                $this->templates->createVersionFromEditor(
                    $appearanceTemplate,
                    $request->user(),
                    (int) $data['base_version'],
                    $this->definition($data['definition']),
                    $versionAssets,
                    $data['summary'] ?? null,
                );
                if ($appearanceTemplate->name !== trim($data['name'])) {
                    $this->templates->rename($appearanceTemplate->fresh(), $request->user(), $data['name']);
                }
            }, 3);
        } catch (Throwable $exception) {
            if ($newAsset) {
                $this->assets->deleteNew($newAsset);
            }
            throw $exception;
        }

        return redirect()->route('appearance-templates.edit', $appearanceTemplate)->with('success', 'Nova versão salva e preservada no histórico.');
    }

    public function setDefault(Request $request, AppearanceTemplate $appearanceTemplate)
    {
        $data = $request->validate(['scope' => ['required', 'in:user,organization']]);
        $this->templates->setDefault($appearanceTemplate, $request->user(), $data['scope'], $request->user()->organization);

        return back()->with('success', 'Template definido como padrão.');
    }

    public function archive(Request $request, AppearanceTemplate $appearanceTemplate)
    {
        $this->templates->archive($appearanceTemplate, $request->user());

        return redirect()->route('appearance-templates.index')->with('success', 'Template arquivado; versões históricas foram preservadas.');
    }

    public function asset(Request $request, AppearanceTemplate $appearanceTemplate, AppearanceTemplateVersion $version, string $key)
    {
        abort_unless($version->appearance_template_id === $appearanceTemplate->id, 404);
        abort_unless(
            AppearanceTemplate::query()->visibleTo($request->user(), $request->user()->organization)
                ->whereKey($appearanceTemplate->id)->exists(),
            404,
        );
        $asset = ($version->assets ?? [])[$key] ?? null;
        abort_unless(is_array($asset), 404);
        $bytes = $this->assets->bytes($asset);

        return response($bytes, 200, [
            'Content-Type' => $asset['mime_type'],
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="appearance-asset"',
        ]);
    }

    /** @return array<string, mixed> */
    private function definition(string $json): array
    {
        try {
            $definition = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['definition' => 'A definição visual enviada é inválida.']);
        }
        if (! is_array($definition)) {
            throw ValidationException::withMessages(['definition' => 'A definição visual precisa ser um objeto.']);
        }

        return $definition;
    }

    /** @return array<string, mixed> */
    private function initialDefinition(string $kind): array
    {
        if ($kind === 'assessment_layout') {
            return ['page' => ['size' => 'A4', 'orientation' => 'portrait', 'margins_mm' => [15, 15, 15, 15]], 'questions' => ['columns' => 1, 'separator' => 'line', 'avoid_break_inside' => true]];
        }

        return [
            'mode' => 'canvas', 'height_mm' => 36,
            'canvas' => ['width_units' => 1000, 'height_units' => 360],
            'elements' => [[
                'id' => 'title', 'type' => 'text', 'token' => 'assessment.title',
                'x' => 40, 'y' => 30, 'width' => 920, 'height' => 70,
                'align' => 'center', 'font_size' => 18, 'font_weight' => 700, 'color' => '#111827',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function editorDefinition(string $kind, array $definition): array
    {
        if ($kind !== 'assessment_header' || ($definition['mode'] ?? null) === 'canvas') {
            return $definition;
        }
        $elements = [];
        foreach ($definition['elements'] ?? [] as $index => $element) {
            $type = $element['type'] ?? 'text';
            if ($type === 'line') {
                $elements[] = [
                    'id' => 'line_'.$index, 'type' => 'line', 'x' => 40, 'y' => 60 + $index * 70,
                    'width' => 920, 'height' => 8, 'border_color' => '#6b7280', 'border_width' => 1,
                ];

                continue;
            }
            $elements[] = array_filter([
                'id' => 'element_'.$index, 'type' => $type, 'token' => $element['token'] ?? null,
                'text' => $element['text'] ?? null, 'x' => 40, 'y' => 25 + $index * 75,
                'width' => 920, 'height' => 58, 'align' => $index === 0 ? 'center' : 'left',
                'font_size' => $index === 0 ? 18 : 11, 'font_weight' => $index === 0 ? 700 : 400,
                'color' => '#111827',
            ], fn (mixed $value): bool => $value !== null);
        }
        $canvasHeight = max(240, min(800, count($elements) * 75 + 35));
        foreach ($elements as &$element) {
            $element['y'] = min($element['y'], $canvasHeight - $element['height']);
        }
        unset($element);

        return [
            'mode' => 'canvas', 'height_mm' => (float) ($definition['height_mm'] ?? 36),
            'canvas' => ['width_units' => 1000, 'height_units' => $canvasHeight],
            'elements' => $elements,
        ];
    }
}
