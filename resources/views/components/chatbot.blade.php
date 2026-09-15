@once
    <link rel="stylesheet" href="{{ asset('css/chatbot.css') }}?v={{ filemtime(public_path('css/chatbot.css')) }}">
@endonce

<section
    class="ai-chat"
    id="ai-chat"
    data-url="{{ route('chatbot.message') }}"
    data-welcome="{{ __('chatbot.welcome') }}"
    data-error="{{ __('chatbot.error') }}"
    data-session-expired="{{ __('chatbot.session_expired') }}"
    data-history="{{ json_encode(auth()->check() ? session('chatbot.history', []) : []) }}"
    aria-label="{{ __('chatbot.title') }}"
>
    <div class="ai-chat__panel" id="ai-chat-panel" hidden>
        <header class="ai-chat__header">
            <div>
                <strong>{{ __('chatbot.title') }}</strong>
                <small>{{ __('chatbot.subtitle') }}</small>
            </div>
            <button class="ai-chat__close" type="button" aria-label="{{ __('chatbot.close') }}">×</button>
        </header>

        <div class="ai-chat__messages" role="log" aria-live="polite"></div>

        <details class="ai-chat__faq">
            <summary>{{ __('chatbot.faq_title') }}</summary>
            <div class="ai-chat__faq-options">
                <strong>{{ __('chatbot_help.title') }}</strong>
                @foreach(__('chatbot_help.items') as $help)
                    <button type="button" data-chat-question>{{ $help['question'] }}</button>
                @endforeach
                <strong>{{ __('chatbot.faq_title') }}</strong>
                @foreach(__('chatbot.faq_questions') as $question)
                    <button type="button" data-chat-question>{{ $question }}</button>
                @endforeach
            </div>
        </details>

        <form class="ai-chat__form">
            @csrf
            <label class="visually-hidden" for="ai-chat-input">{{ __('chatbot.placeholder') }}</label>
            <textarea id="ai-chat-input" maxlength="1500" rows="1" placeholder="{{ __('chatbot.placeholder') }}" required></textarea>
            <button type="submit">{{ __('chatbot.send') }}</button>
        </form>
        <small class="ai-chat__notice">{{ __('chatbot.notice') }}</small>
    </div>
</section>

@once
    <script src="{{ asset('js/chatbot.js') }}?v={{ filemtime(public_path('js/chatbot.js')) }}" defer></script>
@endonce
