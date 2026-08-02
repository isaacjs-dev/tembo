<!-- Section 2.4: Custom Skills -->
<div class="bg-gray-50 border-2 border-duo-border rounded-xl p-6" x-data="customSkillManager()">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-800">Habilidades (Personalizadas)</h2>
            <span class="text-sm font-medium text-gray-500">Crie ou busque habilidades próprias (Opcional).</span>
        </div>
    </div>

    <!-- Selected Chips -->
    <div class="flex flex-wrap gap-2 mb-4" x-show="selectedSkills.length > 0" style="display: none;">
        <template x-for="skill in selectedSkills" :key="skill.id">
            <div
                class="inline-flex items-center bg-secondary/10 text-secondary border border-secondary/20 rounded-full px-4 py-2 text-sm font-bold shadow-sm transition-all">
                <span x-text="skill.name.length > 50 ? skill.name.substring(0, 50) + '...' : skill.name"></span>
                <input type="hidden" name="custom_skills[]" :value="skill.id">
                <button type="button" @click="removeSkill(skill.id)"
                    class="ml-2 hover:text-red-500 focus:outline-none flex items-center justify-center bg-white/50 rounded-full size-5">
                    <span aria-hidden="true" class="material-symbols-outlined text-[14px]">close</span>
                </button>
            </div>
        </template>
    </div>

    <!-- Search / Create Input -->
    <div class="relative w-full">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <span aria-hidden="true" class="material-symbols-outlined text-gray-400">search</span>
        </div>
        <input type="search" x-model="searchQuery" @input.debounce.300ms="searchSkill()"
            aria-label="Buscar habilidade personalizada"
            @keydown.enter.prevent="createSkill()" placeholder="Busque ou digite + Enter para criar nova habilidade..."
            class="block w-full pl-12 pr-4 py-3 bg-white border-2 border-duo-border rounded-xl text-gray-800 focus:outline-none focus:border-secondary focus:ring-0 transition-all font-medium">

        <!-- Dropdown de Sugestoes -->
        <div x-show="searchResults.length > 0 || (searchQuery.length >= 2 && !isSearching)"
            @click.away="searchResults = []" style="display: none;"
            class="absolute z-10 w-full mt-2 bg-white border-2 border-duo-border rounded-xl shadow-lg max-h-60 overflow-y-auto">
            <template x-for="res in searchResults" :key="res.id">
                <div @click="addSkill(res)"
                    class="p-4 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0 transition-colors">
                    <div class="text-sm font-bold text-gray-700 line-clamp-2" x-text="res.name"></div>
                </div>
            </template>

            <!-- Option to Create if query > 2 -->
            <div x-show="searchQuery.length >= 2 && !exactMatchFound" @click="createSkill()"
                class="p-4 hover:bg-secondary/5 cursor-pointer text-secondary transition-colors group flex items-center">
                <span aria-hidden="true"
                    class="material-symbols-outlined mr-2 group-hover:scale-110 transition-transform">add_circle</span>
                <span class="text-sm font-bold">Criar nova habilidade: "<span x-text="searchQuery"></span>"</span>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('customSkillManager', () => ({
            searchQuery: '',
            searchResults: [],
            selectedSkills: [],
            isSearching: false,

            get exactMatchFound() {
                return this.searchResults.some(s => s.name.toLowerCase() === this.searchQuery.toLowerCase().trim());
            },

            async searchSkill() {
                if (this.searchQuery.length < 2) {
                    this.searchResults = [];
                    return;
                }

                this.isSearching = true;
                try {
                    const res = await axios.get('{{ route("institution.custom-skills.search") }}', {
                        params: { q: this.searchQuery }
                    });
                    this.searchResults = res.data.skills;
                } catch (e) {
                    console.error('Erro ao buscar Habilidade Personalizada:', e);
                } finally {
                    this.isSearching = false;
                }
            },

            async createSkill() {
                const query = this.searchQuery.trim();
                if (query.length < 2) return;

                // Checa se ja existe exato no dropdown pra nao criar duplciado
                const existing = this.searchResults.find(s => s.name.toLowerCase() === query.toLowerCase());
                if (existing) {
                    this.addSkill(existing);
                    return;
                }

                try {
                    const res = await axios.post('{{ route("institution.custom-skills.store") }}', {
                        name: query
                    });
                    this.addSkill(res.data.skill);
                } catch (e) {
                    alert('Erro ao criar habilidade. Tente novamente.');
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
                @if(isset($question) && $question->customSkills()->count())
                    this.selectedSkills = @json($question->customSkills->map(function ($s) {
                        return ['id' => $s->id, 'name' => $s->name];
                    })->toArray());
                @endif
            }
        }))
    });
</script>
