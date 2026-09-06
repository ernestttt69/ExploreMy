document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('ai-chat');
    const toggle = document.querySelector('[data-chatbot-toggle]');
    if (!root || !toggle) return;

    const panel = root.querySelector('.ai-chat__panel');
    const close = root.querySelector('.ai-chat__close');
    const form = root.querySelector('.ai-chat__form');
    const input = root.querySelector('textarea');
    const messagesBox = root.querySelector('.ai-chat__messages');
    const submit = form.querySelector('button[type="submit"]');
    const history = [];

    const addMessage = (role, content) => {
        const bubble = document.createElement('div');
        bubble.className = `ai-chat__message ai-chat__message--${role}`;
        bubble.textContent = content;
        messagesBox.appendChild(bubble);
        messagesBox.scrollTop = messagesBox.scrollHeight;
        return bubble;
    };

    const openPanel = () => {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        input.focus();
    };

    const closePanel = () => {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    };

    addMessage('assistant', root.dataset.welcome);
    toggle.addEventListener('click', () => panel.hidden ? openPanel() : closePanel());
    close.addEventListener('click', closePanel);

    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const content = input.value.trim();
        if (!content || submit.disabled) return;

        addMessage('user', content);
        history.push({ role: 'user', content });
        input.value = '';
        submit.disabled = true;
        const loading = addMessage('assistant', '');
        loading.classList.add('ai-chat__message--loading');
        loading.setAttribute('aria-label', 'AI is typing');
        loading.innerHTML = '<span></span><span></span><span></span>';

        try {
            const response = await fetch(root.dataset.url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                },
                body: JSON.stringify({ messages: history.slice(-10).map(message => ({
                    ...message,
                    // Keep full replies visible, but bound AI history to the API's limit.
                    content: message.role === 'assistant'
                        ? Array.from(message.content).slice(0, 1500).join('')
                        : message.content,
                })) }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || root.dataset.error);

            loading.classList.remove('ai-chat__message--loading');
            loading.removeAttribute('aria-label');
            loading.textContent = data.reply;
            history.push({ role: 'assistant', content: data.reply });
            if (history.length > 10) history.splice(0, history.length - 10);
        } catch (error) {
            loading.classList.remove('ai-chat__message--loading');
            loading.removeAttribute('aria-label');
            loading.textContent = error.message || root.dataset.error;
        } finally {
            submit.disabled = false;
            input.focus();
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }
    });
});
