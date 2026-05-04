@php $agent = $aiAgent ?? null; @endphp

<!-- Nome -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ __('Nome do agente') }} <span class="text-red-500">*</span>
    </label>
    <input
        type="text"
        name="name"
        value="{{ old('name', $agent?->name) }}"
        placeholder="{{ __('Ex: Atendente de Vendas, Suporte Técnico...') }}"
        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500"
        required
    >
    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<!-- Descrição -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ __('Descrição') }}
        <span class="text-gray-400 font-normal">({{ __('opcional, para sua referência') }})</span>
    </label>
    <input
        type="text"
        name="description"
        value="{{ old('description', $agent?->description) }}"
        placeholder="{{ __('Ex: Agente para qualificação de leads no funil de vendas') }}"
        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500"
    >
    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<!-- Assistente de Template -->
<div class="rounded-xl border border-purple-200 bg-purple-50 overflow-hidden">

    {{-- Cabeçalho --}}
    <div class="flex items-center gap-2 px-4 py-3 border-b border-purple-100 bg-purple-100/60">
        <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.347.347a3.75 3.75 0 01-5.303 0l-.347-.347z"/>
        </svg>
        <span class="text-sm font-semibold text-purple-800">{{ __('Assistente de prompt') }}</span>
        <span class="ml-auto text-xs text-purple-500">{{ __('Preencha os campos e gere o prompt automaticamente') }}</span>
    </div>

    <div class="p-4 space-y-4">

        {{-- Seleção de tipo --}}
        <div>
            <p class="text-xs font-medium text-gray-600 mb-2">{{ __('1. Selecione o tipo de agente') }}</p>
            <div class="flex flex-wrap gap-2" id="template-type-buttons">
                <button type="button" data-tipo="atendimento"
                    class="template-type-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium border transition
                           bg-white text-gray-600 border-gray-200 hover:border-blue-400 hover:text-blue-700 hover:bg-blue-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3-3-3z"/>
                    </svg>
                    {{ __('Atendimento ao Cliente') }}
                </button>
                <button type="button" data-tipo="vendas"
                    class="template-type-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium border transition
                           bg-white text-gray-600 border-gray-200 hover:border-green-400 hover:text-green-700 hover:bg-green-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('Vendas') }}
                </button>
                <button type="button" data-tipo="suporte"
                    class="template-type-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium border transition
                           bg-white text-gray-600 border-gray-200 hover:border-orange-400 hover:text-orange-700 hover:bg-orange-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    {{ __('Suporte Técnico') }}
                </button>
                <button type="button" data-tipo="cobranca"
                    class="template-type-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium border transition
                           bg-white text-gray-600 border-gray-200 hover:border-red-400 hover:text-red-700 hover:bg-red-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    {{ __('Cobrança') }}
                </button>
                <button type="button" data-tipo="agendamento"
                    class="template-type-btn inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium border transition
                           bg-white text-gray-600 border-gray-200 hover:border-indigo-400 hover:text-indigo-700 hover:bg-indigo-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ __('Agendamento') }}
                </button>
            </div>
        </div>

        {{-- Campos dinâmicos --}}
        <div id="template-fields" class="hidden space-y-4">
            <p class="text-xs font-medium text-gray-600" id="template-fields-label">{{ __('2. Preencha as informações') }}</p>

            {{-- Campos comuns --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        {{ __('Nome do atendente') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="tf_nome" placeholder="{{ __('Ex: Ana, Carlos, Robô...') }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    <p class="mt-0.5 text-xs text-gray-400">{{ __('Como o agente vai se apresentar') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        {{ __('Nome da empresa') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="tf_empresa" placeholder="{{ __('Ex: Loja Estrela, Clínica Saúde...') }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Ramo / setor de atuação') }}</label>
                    <input type="text" id="tf_ramo" placeholder="{{ __('Ex: e-commerce, clínica médica, software...') }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Tom de voz') }}</label>
                    <select id="tf_tom" class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                        <option value="profissional e cordial">{{ __('Profissional e cordial') }}</option>
                        <option value="informal e descontraído">{{ __('Informal e descontraído') }}</option>
                        <option value="formal e técnico">{{ __('Formal e técnico') }}</option>
                        <option value="amigável e entusiasmado">{{ __('Amigável e entusiasmado') }}</option>
                        <option value="neutro e objetivo">{{ __('Neutro e objetivo') }}</option>
                    </select>
                </div>
            </div>

            {{-- Campos específicos: Atendimento --}}
            <div id="tf-group-atendimento" class="tf-group hidden space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        {{ __('Produtos ou serviços que a empresa oferece') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea id="tf_produtos" rows="3"
                        placeholder="{{ __("Ex:\n- Planos de internet residencial (50, 100, 300 Mbps)\n- Suporte técnico incluído\n- Instalação gratuita") }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Horário de atendimento') }}</label>
                        <input type="text" id="tf_horario" placeholder="{{ __('Ex: Seg a Sex, 8h às 18h') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Canais de contato humano') }}</label>
                        <input type="text" id="tf_canais" placeholder="{{ __('Ex: (11) 9999-9999, email@empresa.com') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('O que o agente NÃO deve resolver (escalar para humano)') }}</label>
                    <input type="text" id="tf_nao_resolver" placeholder="{{ __('Ex: cancelamentos, reclamações, reembolsos acima de R$500') }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Perguntas frequentes e respostas') }}</label>
                    <textarea id="tf_faq" rows="3"
                        placeholder="{{ __("Ex:\nP: Como faço para cancelar?\nR: Para cancelamentos, entre em contato com nossa equipe pelo telefone...") }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"></textarea>
                </div>
            </div>

            {{-- Campos específicos: Vendas --}}
            <div id="tf-group-vendas" class="tf-group hidden space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        {{ __('O que você vende (produto/serviço)') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea id="tf_o_que_vende" rows="3"
                        placeholder="{{ __("Ex:\n- Curso online de Marketing Digital (R$497)\n- Mentoria individual (R$1.500/mês)\n- Pacote completo com suporte (R$2.000)") }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Principais diferenciais e benefícios') }}</label>
                    <textarea id="tf_diferenciais" rows="2"
                        placeholder="{{ __("Ex: única plataforma com certificado reconhecido, suporte 24h, garantia de 30 dias") }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Formas de pagamento') }}</label>
                        <input type="text" id="tf_pagamento" placeholder="{{ __('Ex: Pix, cartão em até 12x, boleto') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Público-alvo') }}</label>
                        <input type="text" id="tf_publico" placeholder="{{ __('Ex: empreendedores iniciantes, empresas B2B') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Objetivo principal do agente') }}</label>
                        <select id="tf_objetivo" class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                            <option value="fechar a venda diretamente">{{ __('Fechar a venda diretamente') }}</option>
                            <option value="agendar uma demonstração ou reunião">{{ __('Agendar demonstração / reunião') }}</option>
                            <option value="qualificar o lead e coletar dados de contato">{{ __('Qualificar lead e coletar contato') }}</option>
                            <option value="enviar proposta comercial">{{ __('Enviar proposta comercial') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Política de garantia / devolução') }}</label>
                        <input type="text" id="tf_garantia" placeholder="{{ __('Ex: 7 dias de garantia incondicional') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Tópicos que o agente NÃO deve abordar') }}</label>
                    <input type="text" id="tf_restricoes_vendas" placeholder="{{ __('Ex: preços de concorrentes, descontos acima de 10%, dados de outros clientes') }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                </div>
            </div>

            {{-- Campos específicos: Suporte --}}
            <div id="tf-group-suporte" class="tf-group hidden space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('Nome do produto / sistema') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="tf_produto_nome" placeholder="{{ __('Ex: AppGestor v3, Plataforma XYZ') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Versões / plataformas suportadas') }}</label>
                        <input type="text" id="tf_versoes" placeholder="{{ __('Ex: Android 10+, iOS 15+, Windows 10/11') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        {{ __('Problemas mais comuns e suas soluções') }} <span class="text-red-500">*</span>
                    </label>
                    <textarea id="tf_problemas" rows="4"
                        placeholder="{{ __("Ex:\nProblema: App não abre\nSolução: Desinstale e reinstale o aplicativo. Se persistir, limpe o cache.\n\nProblema: Erro ao fazer login\nSolução: Redefina a senha pelo link 'Esqueci minha senha'") }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Quando escalar para humano') }}</label>
                        <input type="text" id="tf_escalar" placeholder="{{ __('Ex: erros críticos, perda de dados, reembolsos') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Canal de escalonamento') }}</label>
                        <input type="text" id="tf_canal_suporte" placeholder="{{ __('Ex: tickets em suporte.empresa.com, ramal 3000') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Limitações conhecidas do produto (bugs/restrições)') }}</label>
                    <textarea id="tf_limitacoes" rows="2"
                        placeholder="{{ __('Ex: relatórios não exportam em PDF no Safari, backup manual necessário aos domingos') }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"></textarea>
                </div>
            </div>

            {{-- Campos específicos: Cobrança --}}
            <div id="tf-group-cobranca" class="tf-group hidden space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('Tipo de cobrança') }} <span class="text-red-500">*</span>
                        </label>
                        <select id="tf_tipo_cobranca" class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                            <option value="faturas em atraso">{{ __('Faturas em atraso') }}</option>
                            <option value="mensalidades de assinatura">{{ __('Mensalidades de assinatura') }}</option>
                            <option value="parcelas de carnê ou crédito">{{ __('Parcelas de carnê / crédito') }}</option>
                            <option value="cobranças recorrentes">{{ __('Cobranças recorrentes') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Formas de pagamento aceitas') }}</label>
                        <input type="text" id="tf_formas_pagamento" placeholder="{{ __('Ex: Pix, boleto, cartão, transferência') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Pode oferecer desconto ou parcelamento?') }}</label>
                        <input type="text" id="tf_desconto" placeholder="{{ __('Ex: até 10% de desconto à vista, parcelar em 3x sem juros') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Consequência do não pagamento') }}</label>
                        <input type="text" id="tf_consequencia" placeholder="{{ __('Ex: suspensão do serviço, negativação após 30 dias') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Como o cliente regulariza o pagamento') }}</label>
                    <textarea id="tf_como_pagar" rows="2"
                        placeholder="{{ __("Ex: Acesse o link enviado por SMS, ou gere um novo boleto pelo site minhaconta.empresa.com/boleto") }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Quando encaminhar para atendente humano') }}</label>
                    <input type="text" id="tf_escalar_cobranca" placeholder="{{ __('Ex: contestação de cobrança indevida, dívidas acima de R$5.000') }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                </div>
            </div>

            {{-- Campos específicos: Agendamento --}}
            <div id="tf-group-agendamento" class="tf-group hidden space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        {{ __('O que será agendado') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="tf_o_que_agendar" placeholder="{{ __('Ex: consultas médicas, reuniões de vendas, visitas técnicas, aulas particulares') }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Dias e horários disponíveis') }}</label>
                        <input type="text" id="tf_disponibilidade" placeholder="{{ __('Ex: Seg a Sex das 8h às 17h, Sáb das 8h às 12h') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Duração média de cada agendamento') }}</label>
                        <input type="text" id="tf_duracao" placeholder="{{ __('Ex: 30 minutos, 1 hora') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Como confirmar / enviar confirmação') }}</label>
                        <input type="text" id="tf_confirmacao" placeholder="{{ __('Ex: confirmação por este WhatsApp, e-mail automático') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Política de cancelamento / reagendamento') }}</label>
                        <input type="text" id="tf_cancelamento_agendamento" placeholder="{{ __('Ex: avisar com 24h de antecedência') }}"
                            class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Informações que o cliente deve fornecer') }}</label>
                    <input type="text" id="tf_dados_cliente" placeholder="{{ __('Ex: nome completo, CPF, plano de saúde, motivo da consulta') }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Link ou sistema de agenda (se houver)') }}</label>
                    <input type="text" id="tf_link_agenda" placeholder="{{ __('Ex: agenda.empresa.com — o agente pode enviar o link') }}"
                        class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                </div>
            </div>

            {{-- Informações adicionais (comum a todos) --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Informações adicionais / observações') }}</label>
                <textarea id="tf_obs" rows="2"
                    placeholder="{{ __('Qualquer regra extra, contexto ou instrução que o agente deve seguir...') }}"
                    class="tf-field w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"></textarea>
            </div>

            {{-- Botão Gerar --}}
            <div class="flex items-center justify-between pt-1 border-t border-purple-100">
                <p class="text-xs text-gray-400">{{ __('Os campos obrigatórios (*) influenciam diretamente na qualidade do prompt.') }}</p>
                <button type="button" id="btn-gerar-prompt"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    {{ __('Gerar Prompt') }}
                </button>
            </div>
        </div>

    </div>
</div>

<!-- System Prompt -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ __('Prompt do sistema (regras do agente)') }} <span class="text-red-500">*</span>
    </label>
    <p class="text-xs text-gray-500 mb-2">
        {{ __('Gerado automaticamente pelo assistente acima ou escreva manualmente. Quanto mais detalhado, melhor o comportamento.') }}
    </p>
    <textarea
        id="system_prompt"
        name="system_prompt"
        rows="14"
        placeholder="{{ __("Clique em um tipo de agente acima, preencha os campos e clique em \"Gerar Prompt\" — ou escreva diretamente aqui.") }}"
        class="w-full border-gray-300 rounded-md shadow-sm text-sm font-mono focus:ring-purple-500 focus:border-purple-500"
        required
    >{{ old('system_prompt', $agent?->system_prompt) }}</textarea>
    @error('system_prompt')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<!-- Configurações avançadas -->
<div>
    <button type="button" onclick="document.getElementById('advanced-config').classList.toggle('hidden')" class="flex items-center text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
        </svg>
        {{ __('Configurações avançadas (modelo, temperatura)') }}
    </button>
    <div id="advanced-config" class="{{ ($agent?->model || $agent?->temperature || $agent?->max_tokens) ? '' : 'hidden' }} mt-4 p-4 bg-gray-50 rounded-lg space-y-4">
        <p class="text-xs text-gray-500">{{ __('Deixe em branco para usar os valores padrão definidos nas Configurações de IA.') }}</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Modelo') }}</label>
                <select name="model" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
                    <option value="">{{ __('Usar padrão da conta') }}</option>
                    @foreach(\App\Models\AiConfig::availableModels() as $value => $label)
                        <option value="{{ $value }}" {{ old('model', $agent?->model) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Temperatura') }}</label>
                <input type="number" name="temperature" value="{{ old('temperature', $agent?->temperature) }}"
                    min="0" max="1" step="0.05" placeholder="{{ __('Padrão da conta') }}"
                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Máx. tokens') }}</label>
                <input type="number" name="max_tokens" value="{{ old('max_tokens', $agent?->max_tokens) }}"
                    min="50" max="4000" step="50" placeholder="{{ __('Padrão da conta') }}"
                    class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
            </div>
        </div>
    </div>
</div>

<!-- Ativo -->
<div class="flex items-center gap-3">
    <input type="hidden" name="active" value="0">
    <input
        type="checkbox"
        name="active"
        value="1"
        id="active"
        {{ old('active', $agent?->active ?? true) ? 'checked' : '' }}
        class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
    >
    <label for="active" class="text-sm text-gray-700">{{ __('Agente ativo') }}</label>
</div>

<script>
(function () {
    // ── estado ────────────────────────────────────────────────────────────
    let tipoAtivo = null;

    const TYPE_COLORS = {
        atendimento: { active: 'bg-blue-100 text-blue-700 border-blue-400',   idle: 'bg-white text-gray-600 border-gray-200 hover:border-blue-400 hover:text-blue-700 hover:bg-blue-50' },
        vendas:      { active: 'bg-green-100 text-green-700 border-green-400', idle: 'bg-white text-gray-600 border-gray-200 hover:border-green-400 hover:text-green-700 hover:bg-green-50' },
        suporte:     { active: 'bg-orange-100 text-orange-700 border-orange-400', idle: 'bg-white text-gray-600 border-gray-200 hover:border-orange-400 hover:text-orange-700 hover:bg-orange-50' },
        cobranca:    { active: 'bg-red-100 text-red-700 border-red-400',       idle: 'bg-white text-gray-600 border-gray-200 hover:border-red-400 hover:text-red-700 hover:bg-red-50' },
        agendamento: { active: 'bg-indigo-100 text-indigo-700 border-indigo-400', idle: 'bg-white text-gray-600 border-gray-200 hover:border-indigo-400 hover:text-indigo-700 hover:bg-indigo-50' },
    };

    // ── selecionar tipo ───────────────────────────────────────────────────
    document.querySelectorAll('.template-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tipo = btn.dataset.tipo;

            // toggle: clicar no ativo fecha o painel
            if (tipoAtivo === tipo) {
                tipoAtivo = null;
                resetButtons();
                document.getElementById('template-fields').classList.add('hidden');
                return;
            }

            tipoAtivo = tipo;
            resetButtons();

            // destaca botão ativo
            const colors = TYPE_COLORS[tipo] || TYPE_COLORS.atendimento;
            btn.className = btn.className
                .replace(/bg-white|text-gray-600|border-gray-200/g, '')
                .trim();
            colors.active.split(' ').forEach(c => btn.classList.add(c));

            // mostra painel de campos
            document.querySelectorAll('.tf-group').forEach(g => g.classList.add('hidden'));
            const grp = document.getElementById('tf-group-' + tipo);
            if (grp) grp.classList.remove('hidden');

            document.getElementById('template-fields').classList.remove('hidden');
        });
    });

    function resetButtons() {
        document.querySelectorAll('.template-type-btn').forEach(b => {
            const t = b.dataset.tipo;
            const colors = TYPE_COLORS[t] || TYPE_COLORS.atendimento;
            // remove active colors
            colors.active.split(' ').forEach(c => b.classList.remove(c));
            // garante idle base
            if (!b.classList.contains('bg-white')) b.classList.add('bg-white');
            if (!b.classList.contains('text-gray-600')) b.classList.add('text-gray-600');
            if (!b.classList.contains('border-gray-200')) b.classList.add('border-gray-200');
        });
    }

    // ── helpers ───────────────────────────────────────────────────────────
    const v = id => (document.getElementById(id)?.value || '').trim();
    const linha = (label, val) => val ? `- ${label}: ${val}` : '';
    const bloco = (titulo, linhas) => {
        const l = linhas.filter(Boolean).join('\n');
        return l ? `\n${titulo}:\n${l}` : '';
    };

    // ── geradores de prompt ───────────────────────────────────────────────
    const GERADORES = {

        atendimento: (nome, empresa, ramo, tom) => {
            const produtos   = v('tf_produtos');
            const horario    = v('tf_horario');
            const canais     = v('tf_canais');
            const naoResolver = v('tf_nao_resolver');
            const faq        = v('tf_faq');
            const obs        = v('tf_obs');

            return `Você é ${nome}, atendente virtual da empresa ${empresa}${ramo ? ` (${ramo})` : ''}.
Seu tom de comunicação é ${tom}.

IDENTIFICAÇÃO OBRIGATÓRIA:
- Na PRIMEIRA mensagem do cliente, apresente-se:
  "Olá! Meu nome é ${nome}, atendente da ${empresa}. Como posso te ajudar hoje?"
- Nas mensagens seguintes, NUNCA se apresente de novo. Continue a conversa naturalmente.

ENCERRAMENTO OBRIGATÓRIO:
- Ao finalizar (problema resolvido, cliente satisfeito ou despedida), agradeça sempre:
  "Foi um prazer te atender! Obrigado pelo contato com a ${empresa}. Qualquer dúvida, é só chamar. Tenha um ótimo dia! 😊"
${bloco('PRODUTOS E SERVIÇOS', [produtos])}
${bloco('INFORMAÇÕES DE ATENDIMENTO', [
    linha('Horário', horario),
    linha('Canais de contato humano', canais),
    linha('Situações que devem ser escaladas ao humano', naoResolver),
])}${faq ? `\nPERGUNTAS FREQUENTES:\n${faq}` : ''}

REGRAS DE COMPORTAMENTO:
- Responda SEMPRE em português brasileiro, de forma clara e objetiva.
- Seja cordial, empático(a) e paciente em todas as situações.
- Ouça o cliente antes de sugerir soluções.
- Nunca invente informações. Se não souber, diga que vai verificar.
- Use linguagem acessível, sem jargões desnecessários.
- Se o cliente ficar insatisfeito ou o problema estiver fora do seu escopo, encaminhe para atendimento humano.
${obs ? `\nOBSERVAÇÕES ADICIONAIS:\n${obs}` : ''}`.replace(/\n{3,}/g, '\n\n').trim();
        },

        vendas: (nome, empresa, ramo, tom) => {
            const oQueVende    = v('tf_o_que_vende');
            const diferenciais = v('tf_diferenciais');
            const pagamento    = v('tf_pagamento');
            const publico      = v('tf_publico');
            const objetivo     = v('tf_objetivo');
            const garantia     = v('tf_garantia');
            const restricoes   = v('tf_restricoes_vendas');
            const obs          = v('tf_obs');

            return `Você é ${nome}, consultor(a) de vendas da empresa ${empresa}${ramo ? ` (${ramo})` : ''}.
Seu tom de comunicação é ${tom}.

IDENTIFICAÇÃO OBRIGATÓRIA:
- Na PRIMEIRA mensagem do cliente, apresente-se:
  "Olá! Tudo bem? Meu nome é ${nome}, sou consultor(a) de vendas da ${empresa}. Que bom te ter aqui! Como posso te ajudar?"
- Nas mensagens seguintes, NUNCA se apresente de novo. Continue a conversa naturalmente.

ENCERRAMENTO OBRIGATÓRIO:
- Ao finalizar a conversa, agradeça sempre:
  "Obrigado(a) pelo interesse na ${empresa}! Foi um prazer conversar com você. Qualquer dúvida, pode chamar quando quiser. Até logo! 🤝"

OBJETIVO PRINCIPAL:
- Seu foco é ${objetivo}.
${bloco('O QUE VENDEMOS', [oQueVende])}
${bloco('DIFERENCIAIS E BENEFÍCIOS', [diferenciais])}
${bloco('CONDIÇÕES COMERCIAIS', [
    linha('Formas de pagamento', pagamento),
    linha('Garantia / devolução', garantia),
    linha('Público-alvo', publico),
])}

REGRAS DE COMPORTAMENTO:
- Responda SEMPRE em português brasileiro.
- Seja entusiasta e confiante, mas sem pressionar o cliente.
- Entenda a necessidade do cliente ANTES de oferecer qualquer produto.
- Faça perguntas para qualificar: orçamento, prazo, necessidade específica.
- Destaque benefícios concretos, não apenas características técnicas.
- Trate objeções com empatia: ouça, valide e responda com argumentos sólidos.
- Nunca fale mal da concorrência. Foque no valor do que oferecemos.
${restricoes ? `\nTÓPICOS QUE NÃO DEVEM SER ABORDADOS:\n- ${restricoes}` : ''}
${obs ? `\nOBSERVAÇÕES ADICIONAIS:\n${obs}` : ''}`.replace(/\n{3,}/g, '\n\n').trim();
        },

        suporte: (nome, empresa, ramo, tom) => {
            const produtoNome = v('tf_produto_nome');
            const versoes     = v('tf_versoes');
            const problemas   = v('tf_problemas');
            const escalar     = v('tf_escalar');
            const canalSup    = v('tf_canal_suporte');
            const limitacoes  = v('tf_limitacoes');
            const obs         = v('tf_obs');

            return `Você é ${nome}, especialista de suporte técnico da empresa ${empresa}${ramo ? ` (${ramo})` : ''}.
Seu tom de comunicação é ${tom}.

IDENTIFICAÇÃO OBRIGATÓRIA:
- Na PRIMEIRA mensagem do cliente, apresente-se:
  "Olá! Meu nome é ${nome}, do suporte técnico da ${empresa}. Estou aqui para te ajudar. Pode me contar o que está acontecendo?"
- Nas mensagens seguintes, NUNCA se apresente de novo. Continue a conversa naturalmente.

ENCERRAMENTO OBRIGATÓRIO:
- Ao resolver o problema ou finalizar o atendimento, agradeça sempre:
  "Fico feliz em ter conseguido te ajudar! Obrigado pela paciência. Se o problema voltar ou surgir qualquer dúvida, pode chamar. Tenha um ótimo dia! 🛠️"
${bloco('PRODUTO / SISTEMA DE SUPORTE', [
    linha('Nome', produtoNome || empresa),
    linha('Versões/plataformas suportadas', versoes),
])}
${bloco('PROBLEMAS COMUNS E SOLUÇÕES', [problemas])}
${bloco('LIMITAÇÕES CONHECIDAS', [limitacoes])}
${bloco('ESCALONAMENTO', [
    linha('Quando escalar para humano', escalar),
    linha('Canal de escalonamento', canalSup),
])}

REGRAS DE COMPORTAMENTO:
- Responda SEMPRE em português brasileiro, de forma clara e objetiva.
- Demonstre calma e segurança, mesmo diante de clientes frustrados.
- Reconheça o inconveniente ANTES de partir para a solução.
- Explique os passos de forma simples, sem termos técnicos complexos.
- Confirme cada etapa com o cliente antes de continuar.
- Se não souber a solução, diga que vai verificar. Nunca invente.
- Nunca culpe o cliente pelo problema. Foque sempre na solução.
${obs ? `\nOBSERVAÇÕES ADICIONAIS:\n${obs}` : ''}`.replace(/\n{3,}/g, '\n\n').trim();
        },

        cobranca: (nome, empresa, ramo, tom) => {
            const tipoCobranca  = v('tf_tipo_cobranca');
            const formasPagto   = v('tf_formas_pagamento');
            const desconto      = v('tf_desconto');
            const consequencia  = v('tf_consequencia');
            const comoPagar     = v('tf_como_pagar');
            const escalarCob    = v('tf_escalar_cobranca');
            const obs           = v('tf_obs');

            return `Você é ${nome}, agente de cobrança da empresa ${empresa}${ramo ? ` (${ramo})` : ''}.
Seu tom de comunicação é ${tom}.

IDENTIFICAÇÃO OBRIGATÓRIA:
- Na PRIMEIRA mensagem, apresente-se:
  "Olá! Meu nome é ${nome}, da equipe financeira da ${empresa}. Estou entrando em contato sobre ${tipoCobranca || 'um pendência financeira'} em sua conta. Poderia me ajudar a resolver isso?"
- Nas mensagens seguintes, NUNCA se apresente de novo. Continue a conversa naturalmente.

ENCERRAMENTO OBRIGATÓRIO:
- Ao finalizar (pagamento confirmado, acordo fechado ou conversa encerrada), agradeça sempre:
  "Obrigado pela atenção e pela sua colaboração! Qualquer dúvida sobre sua conta, pode entrar em contato. Tenha um ótimo dia!"
${bloco('TIPO DE COBRANÇA', [tipoCobranca])}
${bloco('CONDIÇÕES DE PAGAMENTO', [
    linha('Formas aceitas', formasPagto),
    linha('Descontos / parcelamento disponível', desconto),
    linha('Como regularizar', comoPagar),
])}
${bloco('CONSEQUÊNCIAS DO NÃO PAGAMENTO', [consequencia])}
${bloco('ESCALONAMENTO', [escalarCob])}

REGRAS DE COMPORTAMENTO:
- Responda SEMPRE em português brasileiro.
- Seja firme, mas respeitoso(a) e empático(a). Nunca ameace ou constranja o cliente.
- Ouça as justificativas do cliente antes de apresentar soluções.
- Tente sempre encontrar um acordo viável para ambas as partes.
- Nunca divulgue dados financeiros de outros clientes.
- Se o cliente contestar a dívida como indevida, escalone para atendimento humano imediatamente.
- Informe claramente as consequências do não pagamento, sem exagero.
${obs ? `\nOBSERVAÇÕES ADICIONAIS:\n${obs}` : ''}`.replace(/\n{3,}/g, '\n\n').trim();
        },

        agendamento: (nome, empresa, ramo, tom) => {
            const oQueAgendar  = v('tf_o_que_agendar');
            const disponib     = v('tf_disponibilidade');
            const duracao      = v('tf_duracao');
            const confirmacao  = v('tf_confirmacao');
            const cancelam     = v('tf_cancelamento_agendamento');
            const dadosCliente = v('tf_dados_cliente');
            const linkAgenda   = v('tf_link_agenda');
            const obs          = v('tf_obs');

            return `Você é ${nome}, assistente de agendamento da empresa ${empresa}${ramo ? ` (${ramo})` : ''}.
Seu tom de comunicação é ${tom}.

IDENTIFICAÇÃO OBRIGATÓRIA:
- Na PRIMEIRA mensagem do cliente, apresente-se:
  "Olá! Meu nome é ${nome}, assistente de agendamento da ${empresa}. Vou te ajudar a marcar o seu horário. Pode me dizer o que deseja agendar?"
- Nas mensagens seguintes, NUNCA se apresente de novo. Continue a conversa naturalmente.

ENCERRAMENTO OBRIGATÓRIO:
- Após confirmar o agendamento ou encerrar a conversa, agradeça sempre:
  "Tudo certo! Agendamento confirmado. Obrigado pela preferência pela ${empresa}. Até lá! 😊"
${bloco('O QUE PODE SER AGENDADO', [oQueAgendar])}
${bloco('DISPONIBILIDADE', [
    linha('Dias e horários', disponib),
    linha('Duração média', duracao),
])}
${bloco('PROCESSO DE AGENDAMENTO', [
    linha('Dados necessários do cliente', dadosCliente),
    linha('Como enviar confirmação', confirmacao),
    linha('Link / sistema de agenda', linkAgenda),
    linha('Política de cancelamento', cancelam),
])}

REGRAS DE COMPORTAMENTO:
- Responda SEMPRE em português brasileiro, de forma clara e objetiva.
- Seja atencioso(a) e ágil: o cliente quer marcar o horário com facilidade.
- Colete todas as informações necessárias antes de confirmar.
- Se o horário solicitado não estiver disponível, ofereça imediatamente alternativas próximas.
- Confirme todos os detalhes do agendamento ao final (data, hora, local se aplicável).
- Informe claramente a política de cancelamento para evitar faltas.
${obs ? `\nOBSERVAÇÕES ADICIONAIS:\n${obs}` : ''}`.replace(/\n{3,}/g, '\n\n').trim();
        },
    };

    // ── botão gerar ───────────────────────────────────────────────────────
    document.getElementById('btn-gerar-prompt').addEventListener('click', () => {
        if (!tipoAtivo) return;

        const nome    = v('tf_nome');
        const empresa = v('tf_empresa');

        // valida obrigatórios comuns
        const erros = [];
        if (!nome)    erros.push(document.getElementById('tf_nome'));
        if (!empresa) erros.push(document.getElementById('tf_empresa'));

        // valida obrigatórios do tipo
        const obrigatoriosPorTipo = {
            atendimento: ['tf_produtos'],
            vendas:      ['tf_o_que_vende'],
            suporte:     ['tf_produto_nome', 'tf_problemas'],
            cobranca:    ['tf_tipo_cobranca'],
            agendamento: ['tf_o_que_agendar'],
        };
        (obrigatoriosPorTipo[tipoAtivo] || []).forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.value.trim()) erros.push(el);
        });

        if (erros.length) {
            erros.forEach(el => {
                el.classList.add('border-red-400', 'ring-1', 'ring-red-300');
                el.addEventListener('input', () => el.classList.remove('border-red-400', 'ring-1', 'ring-red-300'), { once: true });
            });
            erros[0].focus();
            erros[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        const ramo = v('tf_ramo');
        const tom  = v('tf_tom');

        const gerador = GERADORES[tipoAtivo];
        if (!gerador) return;

        const promptTextarea = document.getElementById('system_prompt');
        const currentValue   = promptTextarea.value.trim();

        if (currentValue !== '' && !confirm('{{ __("O prompt atual será substituído. Deseja continuar?") }}')) return;

        promptTextarea.value = gerador(nome, empresa, ramo, tom);
        promptTextarea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        promptTextarea.focus();
    });
})();
</script>
