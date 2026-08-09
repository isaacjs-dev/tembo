<?php

namespace App\Http\Controllers;

use App\Models\QuestionResource;
use App\Models\QuestionResourceVersion;
use App\Models\User;
use App\Rules\ActiveOrganizationMember;
use App\Services\QuestionResourceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionResourceController extends Controller
{
    public function index(Request $request, QuestionResourceService $resources): View
    {
        Gate::authorize('viewAny', QuestionResource::class);

        $items = $resources->visibleTo($request->user())
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim($request->string('search')->toString());
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($request->filled('type'), fn (Builder $query) => $query
                ->where('type', $request->string('type')->toString()))
            ->with(['owner:id,name', 'currentVersion:id,question_resource_id,version_number,content'])
            ->withCount('questions')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('question-resources.index', compact('items'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', QuestionResource::class);

        return view('question-resources.create', [
            'resource' => new QuestionResource,
            'teachers' => $this->teachers($request),
        ]);
    }

    public function store(Request $request, QuestionResourceService $resources): RedirectResponse
    {
        Gate::authorize('create', QuestionResource::class);
        $validated = $this->validated($request);
        $organizationId = (int) $request->user()->organization_id;
        $file = $this->storeFile($request, $organizationId);

        try {
            $resource = DB::transaction(function () use ($request, $resources, $validated, $organizationId, $file): QuestionResource {
                $resource = QuestionResource::query()->create([
                    'organization_id' => $organizationId,
                    'owner_id' => $request->user()->id,
                    'title' => $validated['title'],
                    'type' => $validated['type'],
                    'visibility_scope' => $validated['visibility_scope'],
                    'status' => 'active',
                ]);
                $resource->shares()->createMany(
                    collect($validated['teacher_ids'] ?? [])->map(fn ($id) => ['shared_with_user_id' => $id])->all()
                );
                $resources->createVersion($resource, $this->content($validated), $request->user(), $file);

                return $resource;
            });
        } catch (\Throwable $exception) {
            $this->discardStoredFile($file);
            throw $exception;
        }

        return redirect()->route('question-resources.edit', $resource)
            ->with('status', 'Recurso de questão criado e versionado.');
    }

    public function edit(Request $request, QuestionResource $questionResource): View
    {
        Gate::authorize('update', $questionResource);
        $questionResource->load(['versions.creator:id,name', 'shares:id,question_resource_id,shared_with_user_id']);

        return view('question-resources.edit', [
            'resource' => $questionResource,
            'teachers' => $this->teachers($request),
        ]);
    }

    public function update(
        Request $request,
        QuestionResource $questionResource,
        QuestionResourceService $resources,
    ): RedirectResponse {
        Gate::authorize('update', $questionResource);
        $validated = $this->validated($request, $questionResource);
        $shareIds = collect($validated['teacher_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $file = $this->storeFile($request, (int) $questionResource->organization_id);

        try {
            $version = DB::transaction(function () use ($request, $questionResource, $resources, $validated, $shareIds, $file): QuestionResourceVersion {
                $resources->assertLinkedQuestionsCompatible(
                    $questionResource,
                    $validated['visibility_scope'],
                    $shareIds,
                );
                $questionResource->update([
                    'title' => $validated['title'],
                    'visibility_scope' => $validated['visibility_scope'],
                ]);
                $questionResource->shares()->delete();
                $questionResource->shares()->createMany(
                    $shareIds->map(fn ($id) => ['shared_with_user_id' => $id])->all()
                );

                return $resources->createVersion($questionResource, $this->content($validated), $request->user(), $file);
            });
            if (isset($file['storage_path']) && $version->storage_path !== $file['storage_path']) {
                $this->discardStoredFile($file);
            }
        } catch (\Throwable $exception) {
            $this->discardStoredFile($file);
            throw $exception;
        }

        return redirect()->route('question-resources.edit', $questionResource)
            ->with('status', 'Recurso atualizado. As questões existentes continuam fixadas na versão vinculada.');
    }

    public function destroy(QuestionResource $questionResource): RedirectResponse
    {
        Gate::authorize('delete', $questionResource);
        $questionResource->update(['status' => 'archived']);
        $questionResource->delete();

        return redirect()->route('question-resources.index')
            ->with('status', 'Recurso arquivado; vínculos e versões históricas foram preservados.');
    }

    public function download(
        QuestionResource $questionResource,
        QuestionResourceVersion $version,
    ): StreamedResponse {
        Gate::authorize('view', $questionResource);
        abort_unless((int) $version->question_resource_id === (int) $questionResource->id, 404);
        abort_unless($version->storage_disk && $version->storage_path, 404);
        abort_unless(Storage::disk($version->storage_disk)->exists($version->storage_path), 404);

        return Storage::disk($version->storage_disk)->download(
            $version->storage_path,
            basename($version->storage_path),
            ['Content-Type' => $version->mime_type ?: 'application/octet-stream'],
        );
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?QuestionResource $resource = null): array
    {
        $organizationId = (int) $request->user()->organization_id;
        $rules = [
            'title' => ['required', 'string', 'max:180'],
            'visibility_scope' => ['required', Rule::in(['private', 'shared_specific', 'organization'])],
            'teacher_ids' => ['nullable', 'array'],
            'teacher_ids.*' => ['integer', 'distinct', new ActiveOrganizationMember($organizationId, 'teacher')],
            'body' => ['nullable', 'string', 'max:100000'],
            'external_url' => ['nullable', 'url:http,https', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:1000'],
            'metadata_json' => ['nullable', 'json', 'max:50000'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
        if (! $resource) {
            $rules['type'] = ['required', Rule::in(QuestionResource::TYPES)];
        }

        $validated = $request->validate($rules);
        $validated['type'] = $resource?->type ?? $validated['type'];

        if ($validated['visibility_scope'] === 'shared_specific' && empty($validated['teacher_ids'])) {
            throw ValidationException::withMessages([
                'teacher_ids' => 'Selecione ao menos um professor para o compartilhamento específico.',
            ]);
        }
        if ($validated['visibility_scope'] !== 'shared_specific') {
            $validated['teacher_ids'] = [];
        }
        if (blank($validated['body'] ?? null)
            && blank($validated['external_url'] ?? null)
            && ! $request->hasFile('file')
            && ! $resource?->currentVersion?->storage_path) {
            throw ValidationException::withMessages([
                'body' => 'Informe conteúdo estruturado ou uma URL externa para o recurso.',
            ]);
        }

        return $validated;
    }

    /** @param array<string, mixed> $validated */
    private function content(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'external_url' => $validated['external_url'] ?? null,
            'alt_text' => $validated['alt_text'] ?? null,
            'metadata' => filled($validated['metadata_json'] ?? null)
                ? json_decode($validated['metadata_json'], true, 512, JSON_THROW_ON_ERROR)
                : [],
        ];
    }

    private function teachers(Request $request)
    {
        return User::query()
            ->memberOfOrganization((int) $request->user()->organization_id, 'teacher')
            ->where('users.id', '!=', $request->user()->id)
            ->where('users.status', 'active')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name']);
    }

    /** @return array{storage_disk?:string,storage_path?:string,mime_type?:string,size_bytes?:int,sha256?:string} */
    private function storeFile(Request $request, int $organizationId): array
    {
        $upload = $request->file('file');
        if (! $upload) {
            return [];
        }

        $extension = strtolower($upload->guessExtension() ?: $upload->extension() ?: 'bin');
        $path = $upload->storeAs(
            "question-resources/{$organizationId}",
            Str::uuid().".{$extension}",
            'local',
        );

        return [
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => $upload->getMimeType(),
            'size_bytes' => $upload->getSize(),
            'sha256' => hash_file('sha256', $upload->getRealPath()),
        ];
    }

    /** @param array{storage_disk?:string,storage_path?:string} $file */
    private function discardStoredFile(array $file): void
    {
        if (isset($file['storage_disk'], $file['storage_path'])) {
            Storage::disk($file['storage_disk'])->delete($file['storage_path']);
        }
    }
}
