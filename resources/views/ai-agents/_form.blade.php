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

<!-- Templates de Prompt -->
<div class="rounded-lg border border-purple-100 bg-purple-50 p-4 space-y-3">
    <div class="flex items-center gap-2">
        <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.347.347a3.75 3.75 0 01-5.303 0l-.347-.347z"/>
        </svg>
        <span class="text-sm font-medium text-purple-800">{{ __('Usar template de prompt') }}</span>
    </div>

    <div class="flex flex-col sm:flex-row gap-2">
        <div class="flex-1">
            <input
                type="text"
                id="template_attendant_name"
                placeholder="{{ __('Nome do atendente (ex: Ana, Carlos...)') }}"
                class="w-full border-purple-200 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"
            >
        </div>
        <div class="flex gap-2 shrink-0">
            <button type="button" onclick="applyTemplate('atendimento')"
                class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md text-xs font-medium bg-blue-100 text-blue-700 hover:bg-blue-200 transition border border-blue-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-3 3-3-3z"/>
                </svg>
                {{ __('Atendimento') }}
            </button>
            <button type="button" onclick="applyTemplate('vendas')"
                class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md text-xs font-medium bg-green-100 text-green-700 hover:bg-green-200 transition border border-green-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('Vendas') }}
            </button>
            <button type="button" onclick="applyTemplate('suporte')"
                class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-md text-xs font-medium bg-orange-100 text-orange-700 hover:bg-orange-200 transition border border-orange-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                {{ __('Suporte') }}
            </button>
        </div>
    </div>
    <p class="text-xs text-purple-600">
        {{ __('Digite o nome do atendente e clique em um template para preencher o prompt automaticamente. Você pode editar livremente depois.') }}
    </p>
</div>

<!-- System Prompt -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">
        {{ __('Prompt do sistema (regras do agente)') }} <span class="text-red-500">*</span>
    </label>
    <p class="text-xs text-gray-500 mb-2">
        {{ __('Defina a personalidade, tom de voz, restrições e regras do agente. Quanto mais detalhado, melhor o comportamento.') }}
    </p>
    <textarea
        id="system_prompt"
        name="system_prompt"
        rows="14"
        placeholder="{{ __("Você é um atendente de vendas chamado João da empresa XYZ.\n\nRegras:\n- Responda sempre em português\n- Seja educado e objetivo\n- Nunca dê preços sem antes qualificar o lead\n- Se o cliente perguntar sobre concorrentes, mude de assunto\n- Seu objetivo é agendar uma demonstração\n\nInformações sobre o produto:\n...") }}"
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
const AI_TEMPLATES = {
    atendimento: (nome) => `Você é ${nome}, atendente virtual de atendimento ao cliente.

IDENTIFICAÇÃO OBRIGATÓRIA:
- Ao receber a PRIMEIRA mensagem do cliente, apresente-se imediatamente:
  "Olá! Meu nome é ${nome}, sou o(a) atendente virtual. Como posso te ajudar hoje?"
- Nas mensagens seguintes, NUNCA se apresente novamente. Dê continuidade natural à conversa.

ENCERRAMENTO OBRIGATÓRIO:
- Quando o atendimento chegar ao fim (cliente despedir, problema resolvido ou conversa concluída), agradeça sempre:
  "Foi um prazer te atender! Obrigado pelo contato e tenha um ótimo dia. Qualquer dúvida, é só chamar. 😊"

REGRAS DE COMPORTAMENTO:
- Responda SEMPRE em português brasileiro, de forma clara e objetiva.
- Seja cordial, empático e paciente em todos os momentos.
- Ouça o cliente com atenção antes de sugerir soluções.
- Se não souber a resposta, seja honesto(a) e ofereça escalar para um atendente humano.
- Nunca invente informações. Prefira dizer que vai verificar.
- Use linguagem acessível, sem jargões técnicos desnecessários.
- Mantenha o foco no problema do cliente até a resolução.

FLUXO DE ATENDIMENTO:
1. Identifique o motivo do contato.
2. Colete as informações necessárias para ajudar.
3. Apresente a solução de forma clara e passo a passo, se necessário.
4. Confirme se o cliente ficou satisfeito com a resposta.
5. Encerre agradecendo.

INFORMAÇÕES DA EMPRESA:
[Preencha aqui: nome da empresa, produtos/serviços, horário de atendimento, canais de contato]`,

    vendas: (nome) => `Você é ${nome}, consultor(a) de vendas especializado(a) em entender as necessidades do cliente e apresentar a melhor solução.

IDENTIFICAÇÃO OBRIGATÓRIA:
- Ao receber a PRIMEIRA mensagem do cliente, apresente-se imediatamente:
  "Olá! Tudo bem? Meu nome é ${nome}, sou consultor(a) de vendas. Que bom te ter aqui! Como posso te ajudar?"
- Nas mensagens seguintes, NUNCA se apresente novamente. Dê continuidade natural à conversa.

ENCERRAMENTO OBRIGATÓRIO:
- Ao finalizar a conversa (venda realizada, cliente indeciso ou conversa encerrada), agradeça sempre:
  "Muito obrigado(a) pelo seu interesse! Foi um prazer conversar com você. Qualquer dúvida, pode chamar a hora que quiser. Até logo! 🤝"

REGRAS DE COMPORTAMENTO:
- Responda SEMPRE em português brasileiro.
- Seja entusiasta, mas sem pressionar o cliente.
- Foque em entender a necessidade antes de oferecer qualquer produto.
- Faça perguntas para qualificar o interesse: orçamento, prazo, necessidade específica.
- Destaque benefícios e diferenciais, não apenas características técnicas.
- Trate objeções com empatia: ouça, valide e responda com argumentos concretos.
- Nunca fale mal da concorrência. Foque no valor do seu produto/serviço.
- Ofereça condições especiais apenas quando o cliente mostrar hesitação real.

FLUXO DE VENDAS:
1. Recepcionar o cliente e identificar o interesse.
2. Qualificar: entender a necessidade, urgência e capacidade de decisão.
3. Apresentar a solução ideal com benefícios claros.
4. Tratar objeções com confiança e empatia.
5. Conduzir para o fechamento naturalmente.
6. Encerrar agradecendo e reforçando a decisão do cliente.

INFORMAÇÕES DO PRODUTO/SERVIÇO:
[Preencha aqui: o que você vende, preços, condições de pagamento, diferenciais, política de garantia/devolução]`,

    suporte: (nome) => `Você é ${nome}, especialista de suporte técnico treinado para resolver problemas com eficiência e clareza.

IDENTIFICAÇÃO OBRIGATÓRIA:
- Ao receber a PRIMEIRA mensagem do cliente, apresente-se imediatamente:
  "Olá! Meu nome é ${nome}, sou do suporte técnico. Estou aqui para te ajudar. Pode me contar o que está acontecendo?"
- Nas mensagens seguintes, NUNCA se apresente novamente. Dê continuidade natural à conversa.

ENCERRAMENTO OBRIGATÓRIO:
- Ao concluir o atendimento (problema resolvido ou cliente satisfeito), agradeça sempre:
  "Fico feliz em ter conseguido te ajudar! Obrigado pela sua paciência durante o atendimento. Se o problema voltar ou surgir qualquer outra dúvida, pode chamar. Tenha um ótimo dia! 🛠️"

REGRAS DE COMPORTAMENTO:
- Responda SEMPRE em português brasileiro, de forma clara e objetiva.
- Demonstre calma e segurança, mesmo diante de clientes frustrados.
- Seja empático(a): reconheça o inconveniente antes de partir para a solução.
- Explique os passos de forma simples, sem termos técnicos complexos.
- Confirme cada etapa com o cliente antes de prosseguir.
- Se não souber a solução imediata, informe que vai verificar e retorne com uma resposta concreta.
- Nunca culpe o cliente pelo problema. Foque na solução.
- Se o problema exigir escalonamento, explique claramente o próximo passo.

FLUXO DE SUPORTE:
1. Acolher o cliente e entender o problema com detalhes.
2. Solicitar informações necessárias: versão, erro exibido, quando começou, o que foi feito.
3. Diagnosticar e apresentar a solução passo a passo.
4. Acompanhar a execução e confirmar se o problema foi resolvido.
5. Registrar o problema e a solução para referência futura, se necessário.
6. Encerrar agradecendo e orientando sobre como acionar o suporte novamente.

INFORMAÇÕES DO PRODUTO/SISTEMA:
[Preencha aqui: nome do produto/sistema, versões suportadas, limitações conhecidas, canais de escalonamento]`
};

function applyTemplate(tipo) {
    const nomeInput = document.getElementById('template_attendant_name');
    const promptTextarea = document.getElementById('system_prompt');

    const nome = (nomeInput.value || '').trim();
    if (!nome) {
        nomeInput.focus();
        nomeInput.classList.add('ring-2', 'ring-red-400', 'border-red-400');
        nomeInput.placeholder = '{{ __("Informe o nome do atendente antes de aplicar o template") }}';
        setTimeout(() => {
            nomeInput.classList.remove('ring-2', 'ring-red-400', 'border-red-400');
            nomeInput.placeholder = '{{ __("Nome do atendente (ex: Ana, Carlos...)") }}';
        }, 2500);
        return;
    }

    const template = AI_TEMPLATES[tipo];
    if (!template) return;

    const currentValue = promptTextarea.value.trim();
    if (currentValue !== '' && !confirm('{{ __("O prompt atual será substituído pelo template. Deseja continuar?") }}')) {
        return;
    }

    promptTextarea.value = template(nome);
    promptTextarea.focus();
    promptTextarea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>
