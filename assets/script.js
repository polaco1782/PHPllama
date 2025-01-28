const messages = [];

const md = window.markdownit({
    html: true,
    linkify: true,
    typographer: true,
    highlight: function (str, lang) {
        if (lang && hljs.getLanguage(lang)) {
            try {
                const hlvalue = hljs.highlight(str, { language: lang, ignoreIllegals: true }).value;
                return `<pre class="hljs"><code>${hlvalue}</code></pre>`;
            } catch (_) {}
        }
        return '<pre class="hljs"><code>' + md.utils.escapeHtml(str) + '</code></pre>';
    }
}).use(window.markdownitMathjax());


const escapeHtml = (str) => {
    return str.replace(/["&'<>]/g, (match) => ({
        '"': '&quot;',
        '&': '&amp;', 
        "'": '&#39;', 
        '<': '&lt;', 
        '>': '&gt;'
    }[match]));
}

const addChatMessage = (user, message, cot, extraClass) => {
    removeMessageInfos();
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

    messageEl.classList.add('chat-message', 'user-message', 'p-2', 'rounded', 'bg-secondary', 'shadow', user, extraClass);

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

const addMessageInfos = (info, type) => {
    const chatContainer = document.querySelector('#chat-history');
    const loadingEl = document.createElement('div');
    loadingEl.classList.add('p-2', 'rounded', 'bg-secondary', 'shadow', 'chat-message', 'info-message', `info-message-${type}`);
    loadingEl.textContent = info;
    chatContainer.appendChild(loadingEl);
}

const removeMessageInfos = () => document.querySelectorAll('.info-message').forEach((el) => el.remove());

const toggleEnableChat = (enable) => {
    document.querySelectorAll('#submit, #prompt-input').forEach((element) => {
        element[enable ? 'removeAttribute' : 'setAttribute']('disabled', 'disabled');
    });
    document.querySelector('#prompt-input').focus();
};

const toggleLoading = (enable) => {
    toggleEnableChat(!enable);
    console.log('loading', enable)
    if (enable) {
        console.log('adding')
        addMessageInfos('Generating response...', 'warning');
    }
}

const toFormData = (object) => {
    const formData = new FormData();
    for (let i in object) {
        formData.append(i, object[i]);
    }
    return formData; 
};

document.getElementById('chat-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const promptInput = document.getElementById('prompt-input');
    const chatHistory = document.getElementById('chat-history');
    const prompt = promptInput.value.trim();
    
    if (!prompt) {
        return;
    }

    addChatMessage('user', prompt, '');

    chatHistory.scrollTop = chatHistory.scrollHeight;
    promptInput.value = '';

    toggleLoading(true);

    fetch('', {
        method: 'POST',
        body: toFormData({
            action: 'generate',
            prompt,
            model: document.querySelector('#model-select').value,
            history: JSON.stringify(messages),
        })
    })
    .then(response => response.json())
    .then(data => {
        addChatMessage('assistant', data.response, data.chainOfThought.length > 0 ? data.chainOfThought : '');
        if (window.MathJax) {
            window.MathJax.typeset();
        }
        toggleLoading(false);
    })
    .catch(() => {
        toggleLoading(false);
        addMessageInfos('Failed to generate.', 'error');
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
