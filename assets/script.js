const messages = [];

const md = window.markdownit({
    html: true,
    linkify: true,
    typographer: true,
    highlight: function (str, lang) {
        if (lang && hljs.getLanguage(lang)) {
            try {
                return '<pre class="hljs"><code>' +
                        hljs.highlight(str, { language: lang, ignoreIllegals: true }).value +
                    '</code></pre>';
            } catch (__) {}
        }
        return '<pre class="hljs"><code>' + md.utils.escapeHtml(str) + '</code></pre>';
    }
}).use(window.markdownitMathjax());

const escapeHtml = (str) => {
    return str.replace(/["&'<>]/g, function(match) {
        switch(match) {
            case '"': return '&quot;';
            case '&': return '&amp;';
            case "'": return '&#39;';
            case '<': return '&lt;';
            case '>': return '&gt;';
        }
    });
}

const addChatMessage = (user, message, cot) => {
    messages.push({
        role: user,
        content: message,
    })

    const chatContainer = document.querySelector('#chat-history');
    const modelSelect = document.querySelector('#model-select');
    const messageEl = document.createElement('div');
    const id = (Math.random() + 1).toString(36).substring(2);
    let name = user === 'user' ? 'You' : 'AI';
    let content = `<strong>${name} (${modelSelect.value}):</strong>`;

    messageEl.classList.add(user, 'chat-message', 'user-message', 'p-2', 'rounded', 'bg-secondary', 'shadow', user);

    if (cot.trim() && cot !== '<br>\n<br>') {
        content += `<b id="tt_${id}" data-toggle="tooltip" data-placement="top" title="${escapeHtml(cot)}">🤔</b>`;
    }
    
    if (user === 'user') {
        messageEl.innerHTML = content + `<br/>${escapeHtml(message)}`;
    } else {
        messageEl.innerHTML = content + `<br/>${md.render(message)}`;
    }
    chatContainer.appendChild(messageEl);
    cot && new bootstrap.Tooltip(document.querySelector(`#tt_${id}`));
    chatContainer.scrollTop = chatContainer.scrollHeight;
};

document.getElementById('chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const promptInput = document.getElementById('prompt-input');
    const chatHistory = document.getElementById('chat-history');
    const prompt = promptInput.value.trim();
    
    if (!prompt) return;

    addChatMessage('user', prompt, '');

    const loadingEl = document.createElement('div');
    loadingEl.classList.add('chat-message', 'loading-message');
    loadingEl.textContent = 'Generating response...';
    chatHistory.appendChild(loadingEl);

    chatHistory.scrollTop = chatHistory.scrollHeight;
    promptInput.value = '';

    const formData = new FormData();
    formData.append('action', 'generate');
    formData.append('prompt', prompt);
    formData.append('model', document.querySelector('#model-select').value);
    formData.append('history', JSON.stringify(messages));

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        chatHistory.removeChild(loadingEl);

        addChatMessage('assistant', data.response, data.chainOfThought.length > 0 ? data.chainOfThought : '');

        if (window.MathJax) {
            window.MathJax.typeset();
        }
    })
    .catch(error => {
        if (loadingEl.parentNode) {
            chatHistory.removeChild(loadingEl);
        }

        console.error(error); // If you see that fucking shit, please: debug > code > commit > push :D

        const errorEl = document.createElement('div');
        errorEl.classList.add('chat-message', 'error-message');
        errorEl.textContent = 'Error generating response';
        chatHistory.appendChild(errorEl);
    });
});

document.getElementById('prompt-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        const form = document.getElementById('chat-form');
        const event = new Event('submit', { cancelable: true });
        if (form.dispatchEvent(event)) {
            form.querySelector('button[type="submit"]').click();
        }
    }
});
