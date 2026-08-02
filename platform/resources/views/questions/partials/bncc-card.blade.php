<!-- Section 2.5: BNCC (Opcional) -->
<div class="bg-gray-50 border-2 border-duo-border rounded-xl p-6" x-data="bnccManager()">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-800">Classificação BNCC</h2>
            <span class="text-sm font-medium text-gray-500">Busque e vincule habilidades (Opcional).</span>
        </div>
    </div>

    <!-- Selected Chips -->
    <div class="flex flex-wrap gap-2 mb-4" x-show="selectedSkills.length > 0" style="display: none;">
        <template x-for="skill in selectedSkills" :key="skill.id">
            <div
                class="inline-flex items-center bg-primary/10 text-primary border border-primary/20 rounded-full px-4 py-2 text-sm font-bold shadow-sm transition-all">
                <span
                    x-text="skill.code + ' - ' + (skill.title.length > 40 ? skill.title.substring(0, 40) + '...' : skill.title)"></span>
                <input type="hidden" name="bncc_skills[]" :value="skill.id">
                <button type="button" @click="removeSkill(skill.id)"
                    class="ml-2 hover:text-red-500 focus:outline-none flex items-center justify-center bg-white/50 rounded-full size-5">
                    <span aria-hidden="true" class="material-symbols-outlined text-[14px]">close</span>
                </button>
            </div>
        </template>
    </div>

    <!-- Search Input -->
    <div class="relative w-full">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <span aria-hidden="true" class="material-symbols-outlined text-gray-400">search</span>
        </div>
        <input type="search" x-model="searchQuery" @input.debounce.500ms="searchNode()"
            aria-label="Buscar habilidade ou código da BNCC"
            placeholder="Busque habilidades por código (ex: EF06MA01) ou palavra-chave..."
            class="block w-full pl-12 pr-4 py-3 bg-white border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-primary focus:ring-0 transition-all font-medium">

        <!-- Dropdown de Sugestoes -->
        <div x-show="searchResults.length > 0" @click.away="searchResults = []" style="display: none;"
            class="absolute z-10 w-full mt-2 bg-white border-2 border-duo-border rounded-xl shadow-lg max-h-60 overflow-y-auto">
            <template x-for="res in searchResults" :key="res.id">
                <div @click="addSkill(res)"
                    class="p-4 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0 transition-colors">
                    <div class="font-extrabold text-sm text-primary mb-1" x-text="res.code"></div>
                    <div class="text-sm text-gray-600 line-clamp-2" x-text="res.title"></div>
                </div>
            </template>
            <div x-show="searchResults.length === 0 && searchQuery.length >= 2"
                class="p-4 text-sm text-gray-500 text-center">
                Nenhuma habilidade encontrada para os filtros atuais.
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('bnccManager', () => ({
            searchQuery: '',
            searchResults: [],
            selectedSkills: [],

            async searchNode() {
                if (this.searchQuery.length < 2) {
                    this.searchResults = [];
                    return;
                }

                // Pegar os valores atuais do formulário para refinar a busca
                const dSelect = document.getElementById('discipline_id');
                const sSelect = document.getElementById('stage');
                const gSelect = document.getElementById('grade');

                const dId = dSelect ? dSelect.value : '';
                const stg = sSelect ? sSelect.value : '';
                const grd = gSelect ? gSelect.value : '';

                try {
                    const res = await axios.get('{{ route("institution.bncc.search") }}', {
                        params: {
                            q: this.searchQuery,
                            discipline_id: dId,
                            stage: stg,
                            grade: grd
                        }
                    });
                    this.searchResults = res.data.skills;
                } catch (e) {
                    console.error('Erro ao buscar BNCC:', e);
                }
            },

            addSkill(skill) {
                if (!this.selectedSkills.find(s => s.id === skill.id)) {
                    this.selectedSkills.push(skill);
                }
                this.searchQuery = '';
                this.searchResults = [];
            },

            removeSkill(id) {
                this.selectedSkills = this.selectedSkills.filter(s => s.id !== id);
            },

            init() {
                // Hydrate on edit
                @if(isset($question) && $question->bnccSkills->count())
                    this.selectedSkills = @json($question->bnccSkills->map(function ($s) {
                        return ['id' => $s->id, 'code' => $s->code, 'title' => $s->title];
                    })->toArray());
                @endif
            }
        }))
    });
</script>
