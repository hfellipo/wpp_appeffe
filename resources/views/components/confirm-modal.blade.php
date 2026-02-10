@props([
    'name' => 'confirm-modal',
])

<div
    x-data="{
        show: false,
        message: '',
        formId: null
    }"
    x-on:open-confirm.window="
        if ($event.detail.name === '{{ $name }}') {
            formId = $event.detail.formId || null;
            var formEl = formId ? document.getElementById(formId) : null;
            message = $event.detail.message || (formEl && formEl.getAttribute('data-confirm-message')) || '';
            show = true;
        }
    "
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: none;"
>
    <div
        x-show="show"
        class="fixed inset-0 bg-gray-500/75 transition-opacity"
        x-transition:enter="ease-out duration-200"
        x-transition:leave="ease-in duration-150"
        x-on:click="show = false"
    ></div>
    <div
        x-show="show"
        class="relative bg-white rounded-lg shadow-xl max-w-sm mx-auto p-6 mt-12"
        x-transition:enter="ease-out duration-200"
        x-transition:leave="ease-in duration-150"
        x-on:click.stop
    >
        <p class="text-sm text-gray-600" x-text="message"></p>
        <div class="mt-6 flex justify-end gap-2">
            <button
                type="button"
                x-on:click="show = false"
                class="px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200"
            >
                {{ __('Cancelar') }}
            </button>
            <button
                type="button"
                x-on:click="
                    if (formId && document.getElementById(formId)) {
                        document.getElementById(formId).submit();
                    }
                    show = false;
                "
                class="px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700"
            >
                {{ __('Confirmar') }}
            </button>
        </div>
    </div>
</div>
