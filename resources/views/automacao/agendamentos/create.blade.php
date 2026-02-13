<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Agendar post') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <p class="mb-4">
                <a href="{{ route('automacao.agendamentos.index') }}" class="text-brand-600 hover:text-brand-800 text-sm">← {{ __('Voltar para Posts agendados') }}</a>
            </p>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form action="{{ route('automacao.agendamentos.store') }}" method="POST" id="form-agendar" enctype="multipart/form-data">
                        @csrf
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="scheduled_date" :value="__('Data')" />
                                    <x-text-input
                                        id="scheduled_date"
                                        name="scheduled_date"
                                        type="date"
                                        class="mt-1 block w-full"
                                        :value="old('scheduled_date')"
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
                                        :value="old('scheduled_time')"
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
                                            <input type="radio" name="target_type" value="{{ $value }}" {{ old('target_type', 'group') === $value ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                            <span class="text-sm">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div id="target-group-wrap" class="target-wrap" style="{{ old('target_type', 'group') !== 'group' ? 'display:none' : '' }}">
                                <x-input-label for="target_group_id" :value="__('Grupo WhatsApp')" />
                                <select id="target_group_id" name="target_group_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">— {{ __('Selecione o grupo') }} —</option>
                                    @foreach($groups as $g)
                                        <option value="{{ $g->id }}" {{ (string) old('target_group_id') === (string) $g->id ? 'selected' : '' }}>
                                            {{ trim($g->custom_contact_name ?? '') ?: trim($g->contact_name ?? '') ?: $g->peer_jid }}
                                        </option>
                                    @endforeach
                                </select>
                                @if($groups->isEmpty())
                                    <p class="mt-1 text-sm text-amber-600">{{ __('Nenhum grupo encontrado. Abra o WhatsApp e sincronize as conversas.') }}</p>
                                @endif
                            </div>

                            <div id="target-list-wrap" class="target-wrap" style="{{ old('target_type') !== 'list' ? 'display:none' : '' }}">
                                <x-input-label for="target_list_id" :value="__('Lista')" />
                                <select id="target_list_id" name="target_list_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">— {{ __('Selecione a lista') }} —</option>
                                    @foreach($listas as $l)
                                        <option value="{{ $l->id }}" {{ (string) old('target_list_id') === (string) $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="target-tag-wrap" class="target-wrap" style="{{ old('target_type') !== 'tag' ? 'display:none' : '' }}">
                                <x-input-label for="target_tag_id" :value="__('Tag')" />
                                <select id="target_tag_id" name="target_tag_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">— {{ __('Selecione a tag') }} —</option>
                                    @foreach($tags as $t)
                                        <option value="{{ $t->id }}" {{ (string) old('target_tag_id') === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="image" :value="__('Imagem (opcional)')" />
                                <label for="image" class="mt-1 flex flex-col items-center justify-center w-full min-h-[120px] border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <input
                                        id="image"
                                        name="image"
                                        type="file"
                                        accept="image/jpeg,image/png,image/gif,image/webp"
                                        class="hidden"
                                    />
                                    <span class="flex flex-col items-center gap-1 text-gray-500">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="text-sm font-medium text-gray-600">{{ __('Clique para escolher imagem') }}</span>
                                        <span class="text-xs text-gray-400">{{ __('JPG, PNG, GIF ou WebP — máx. 5 MB') }}</span>
                                    </span>
                                    <span id="image-file-name" class="mt-2 text-sm text-brand-600 font-medium hidden"></span>
                                </label>
                                <p class="mt-1 text-xs text-gray-500">{{ __('Se enviar imagem, o texto abaixo será a legenda (aparece abaixo da imagem no WhatsApp). Máx. 5 MB.') }}</p>
                                <x-input-error :messages="$errors->get('image')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="message" :value="__('Mensagem / Legenda')" />
                                <textarea
                                    id="message"
                                    name="message"
                                    rows="6"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                    placeholder="{{ __('Digite o texto. Com imagem, vira legenda. Pode incluir link.') }}"
                                >{{ old('message') }}</textarea>
                                <x-input-error :messages="$errors->get('message')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 mt-6">
                            <a href="{{ route('automacao.agendamentos.index') }}" class="btn-secondary">{{ __('Cancelar') }}</a>
                            <x-primary-button type="submit">{{ __('Agendar envio') }}</x-primary-button>
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

            var imageInput = document.getElementById('image');
            var fileNameSpan = document.getElementById('image-file-name');
            if (imageInput && fileNameSpan) {
                imageInput.addEventListener('change', function () {
                    var name = this.files && this.files.length ? this.files[0].name : '';
                    fileNameSpan.textContent = name ? name : '';
                    fileNameSpan.classList.toggle('hidden', !name);
                });
            }
        });
    </script>
</x-app-layout>
