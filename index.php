<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

class OllamaUI {
    private $ollamaUrl = 'http://localhost:11434/api';
    private $selectedModel = '';
    private $startupDirective = 'I am an AI assistant. My task is to help the user and provide information on their request. '.
                                'I am polite and helpful. I am not rude or offensive, and brief as possible in my responses, '.
                                'unless the user asks for more information. My name is actually PHPllama, when asked for.';

    public function __construct() {
        if (!function_exists('curl_init')) {
            print('<h2>Your PHP installation is broken! cURL is not installed on this server!</h2>');
            exit;
        }
    }

    public function listModels() {
        $ch = curl_init($this->ollamaUrl . '/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if (empty($response) || $response === false) {
            return [['name' => 'Failed to fetch models!']];
        }

        return json_decode($response, true)['models'];
    }

    public function generateChatResponse($prompt, $model, $history = []) {
        $this->selectedModel = $model;
        $ch = curl_init($this->ollamaUrl . '/chat');

        // Add startup directive to history
        $history = array_merge([[
            'role' => 'assistant',
            'content' => $this->startupDirective
        ]], $history);

        $messages = array_map(function($msg) {
            return [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }, $history);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $this->selectedModel,
            'messages' => $messages,
            'stream' => false
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);

        return $this->parseResponse($response);
    }

    private function parseResponse($response) {
        $json = json_decode($response, true);
        $fullResponse = $json['message']['content'] ?? 'No response generated';
        
        // Extract chain of thought
        preg_match('/<think>(.*?)<\/think>/s', $fullResponse, $matches);
        $chainOfThought = $matches[1] ?? '';
        $cleanResponse = preg_replace('/<think>.*?<\/think>/s', '', $fullResponse);

        return [
            'response' => trim($cleanResponse),
            'chainOfThought' => trim($chainOfThought),
        ];
    }

    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'generate') {
                $prompt = $_POST['prompt'];
                $model = $_POST['model'];
                $history = isset($_POST['history']) ? json_decode($_POST['history'], true) : [];
                if (!empty($prompt)) {
                    echo json_encode($this->generateChatResponse($prompt, $model, $history));
                    exit;
                }
            }
        }
        return ['models' => $this->listModels()];
    }

    public function __debug_stderr($message) {
        $fp = fopen('php://stderr', 'w');
        if (is_array($message) || is_object($message)) {
            fputs($fp, print_r($message, true) . "\n");
        } else {
            fputs($fp, $message . "\n");
        }
        fclose($fp);
    }
}

$ui = new OllamaUI();
$data = $ui->handleRequest();

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>PHPllama Chat Web UI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/default.min.css">
</head>
<body>
    <div class="container d-flex flex-column" style="height: 100vh;">
        <h1 flex-grow-1 class="flex-grow-1 text-center">PHPllama Chat Web UI</h1>
        <div id="chat-history" class="flex-grow-1 text-light rounded p-2 overflow-auto" style="height: 50vh;"></div>
        <form id="chat-form" class="flex-grow-1">
            <label for="model-select" class="form-label">Model</label>
            <select class="form-control text-dark bg-white" id="model-select" name="model">
                <?php foreach ($data['models'] as $model): ?>
                    <option value="<?= htmlspecialchars($model['name']) ?>">
                        <?= htmlspecialchars($model['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br/>
            <label for="prompt-input" class="form-label">Prompt</label>
            <textarea class="form-control text-dark bg-white" id="prompt-input" name="prompt" rows="4" placeholder="Enter your prompt here" autofocus></textarea>
            <br/>
            <button type="submit" class="btn btn-primary w-100">Send</button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/markdown-it@14.0.0/dist/markdown-it.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/markdown-it-mathjax@2.0.0/markdown-it-mathjax.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript">
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

        // Simple content processing function - markdown-it-mathjax handles both markdown and math
        const processContent = (content) => {
            return md.render(content);
        };

        const sanitizeForHtmlAttribute = (str) => {
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

        const escapeHTML = (toOutput) => {
            return toOutput.replace(/\&/g, '&amp;')
                .replace(/\</g, '&lt;')
                .replace(/\>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/\'/g, '&#x27;')
                .replace(/\//g, '&#x2F;');
        }

        document.getElementById('chat-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const promptInput = document.getElementById('prompt-input');
            const chatHistory = document.getElementById('chat-history');
            const modelSelect = document.getElementById('model-select');
            const prompt = promptInput.value.trim();
            
            if (!prompt) return;

            const userMessageEl = document.createElement('div');
            userMessageEl.classList.add('chat-message', 'user-message', 'p-2', 'm-1', 'rounded', 'bg-secondary', 'shadow');
            userMessageEl.style = "border: 1px solid #eee; border-left-width: 5px;"
            userMessageEl.innerHTML = `<strong>You (${modelSelect.value}):</strong> <br/>${escapeHTML(prompt)}`;
            chatHistory.appendChild(userMessageEl);

            const loadingEl = document.createElement('div');
            loadingEl.classList.add('chat-message', 'loading-message');
            loadingEl.textContent = 'Generating response...';
            chatHistory.appendChild(loadingEl);

            chatHistory.scrollTop = chatHistory.scrollHeight;
            promptInput.value = '';

            const chatMessages = Array.from(chatHistory.querySelectorAll('.chat-message'))
                .filter(el => el !== loadingEl)
                .map(el => {
                    const role = el.classList.contains('user-message') ? 'user' : 'assistant';
                    const content = el.textContent.replace(/^You \(.*?\): |^AI \(.*?\): /, '').trim();
                    return { role, content };
                });

            const formData = new FormData();
            formData.append('action', 'generate');
            formData.append('prompt', prompt);
            formData.append('model', modelSelect.value);
            formData.append('history', JSON.stringify(chatMessages));

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                chatHistory.removeChild(loadingEl);

                const aiMessageEl = document.createElement('div');
                aiMessageEl.classList.add('chat-message', 'ai-message', 'p-2', 'm-1', 'rounded', 'bg-secondary', 'shadow');
                aiMessageEl.style = "border: 1px solid #003383; border-left-width: 5px;"

                const id = (Math.random() + 1).toString(36).substring(2);

                // Create COT button if chain of thought exists
                let cotButton = '';
                if (data.chainOfThought.trim() && data.chainOfThought !== '<br>\n<br>') {
                    cotButton = `<b id="tt_${id}" data-toggle="tooltip" data-placement="top" title="${sanitizeForHtmlAttribute(data.chainOfThought)}">🤔</b>`;
                }

                aiMessageEl.innerHTML = `<strong>AI (${modelSelect.value}):</strong> 
                    ${cotButton}
                    <div class="message-content">${processContent(data.response)}</div>`;

                chatHistory.appendChild(aiMessageEl);
                if(data.chainOfThought.length > 0) {
                    new bootstrap.Tooltip(document.querySelector(`#tt_${id}`));
                }
                chatHistory.scrollTop = chatHistory.scrollHeight;

                //Initialize MathJax after adding new content
                if (window.MathJax) {
                    window.MathJax.typeset();
                }
            })
            .catch(error => {
                if (loadingEl.parentNode) {
                    chatHistory.removeChild(loadingEl);
                }

                console.error(error);

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
    </script>
</body>
</html>