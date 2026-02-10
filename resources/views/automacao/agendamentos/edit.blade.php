<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Reconfigurar post') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <p class="mb-4">
                <a href="{{ route('automacao.agendamentos.index') }}" class="text-brand-600 hover:text-brand-800 text-sm">← {{ __('Voltar para Posts agendados') }}</a>
            </p>

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">{{ session('success') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('automacao.agendamentos.update', $post) }}" method="POST" id="form-agendar" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="scheduled_date" :value="__('Data')" />
                                    <x-text-input
                                        id="scheduled_date"
                                        name="scheduled_date"
                                        type="date"
                                        class="mt-1 block w-full"
                                        :value="old('scheduled_date', $post->scheduled_at->format('Y-m-d'))"
                                        required
                                    />
                                    <x-input-error :messages="$errors->get('scheduled_date')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="scheduled_time" :value="__('Horário')" />
                                    <x-text-input
                                        id="scheduled_time"
                                        name="scheduled_time"
                                        type="time"
                                        class="mt-1 block w-full"
                                        :value="old('scheduled_time', $post->scheduled_at->format('H:i'))"
                                        required
                                    />
                                    <x-input-error :messages="$errors->get('scheduled_time')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label :value="__('Enviar para')" />
                                <div class="mt-2 space-y-2">
                                    @foreach($targetTypes as $value => $label)
                                        <label class="inline-flex items-center gap-2 mr-4">
                                            <input type="radio" name="target_type" value="{{ $value }}" {{ old('target_type', $post->target_type) === $value ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                            <span class="text-sm">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div id="target-group-wrap" class="target-wrap" style="{{ old('target_type', $post->target_type) !== 'group' ? 'display:none' : '' }}">
                                <x-input-label for="target_group_id" :value="__('Grupo WhatsApp')" />
                                <select id="target_group_id" name="target_group_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">— {{ __('Selecione o grupo') }} —</option>
                                    @foreach($groups as $g)
                                        <option value="{{ $g->id }}" {{ (string) old('target_group_id', $post->target_type === 'group' ? $post->target_id : '') === (string) $g->id ? 'selected' : '' }}>
                                            {{ trim($g->custom_contact_name ?? '') ?: trim($g->contact_name ?? '') ?: $g->peer_jid }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="target-list-wrap" class="target-wrap" style="{{ old('target_type', $post->target_type) !== 'list' ? 'display:none' : '' }}">
                                <x-input-label for="target_list_id" :value="__('Lista')" />
                                <select id="target_list_id" name="target_list_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">— {{ __('Selecione a lista') }} —</option>
                                    @foreach($listas as $l)
                                        <option value="{{ $l->id }}" {{ (string) old('target_list_id', $post->target_type === 'list' ? $post->target_id : '') === (string) $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="target-tag-wrap" class="target-wrap" style="{{ old('target_type', $post->target_type) !== 'tag' ? 'display:none' : '' }}">
                                <x-input-label for="target_tag_id" :value="__('Tag')" />
                                <select id="target_tag_id" name="target_tag_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">— {{ __('Selecione a tag') }} —</option>
                                    @foreach($tags as $t)
                                        <option value="{{ $t->id }}" {{ (string) old('target_tag_id', $post->target_type === 'tag' ? $post->target_id : '') === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="image" :value="__('Imagem')" />
                                @if($post->image_path)
                                    <p class="mt-1 text-sm text-gray-600">{{ __('Imagem atual:') }} 📷 {{ basename($post->image_path) }}</p>
                                    <p class="text-xs text-gray-500">{{ __('Envie outra para substituir.') }}</p>
                                @endif
                                <input
                                    id="image"
                                    name="image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/gif,image/webp"
                                    class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100"
                                />
                                <x-input-error :messages="$errors->get('image')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="message" :value="__('Mensagem / Legenda')" />
                                <textarea
                                    id="message"
                                    name="message"
                                    rows="6"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                >{{ old('message', $post->message) }}</textarea>
                                <x-input-error :messages="$errors->get('message')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 mt-6">
                            <a href="{{ route('automacao.agendamentos.index') }}" class="btn-secondary">{{ __('Cancelar') }}</a>
                            <x-primary-button type="submit">{{ __('Salvar alterações') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('form-agendar');
            var radios = form.querySelectorAll('input[name="target_type"]');
            var groupWrap = document.getElementById('target-group-wrap');
            var listWrap = document.getElementById('target-list-wrap');
            var tagWrap = document.getElementById('target-tag-wrap');

            function toggleTarget() {
                var v = form.querySelector('input[name="target_type"]:checked').value;
                groupWrap.style.display = v === 'group' ? 'block' : 'none';
                listWrap.style.display = v === 'list' ? 'block' : 'none';
                tagWrap.style.display = v === 'tag' ? 'block' : 'none';
                document.getElementById('target_group_id').disabled = v !== 'group';
                document.getElementById('target_list_id').disabled = v !== 'list';
                document.getElementById('target_tag_id').disabled = v !== 'tag';
            }

            radios.forEach(function (r) { r.addEventListener('change', toggleTarget); });
            toggleTarget();
        });
    </script>
</x-app-layout>
