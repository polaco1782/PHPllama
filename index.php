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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex/dist/katex.min.css">
</head>
<body>
    <div class="container d-flex flex-column" style="height: 100vh;">
        <h1 flex-grow-1 class="flex-grow-1 text-center">PHPllama Chat Web UI</h1>
        <div id="chat-history" class="flex-grow-1 text-light bg-secondary rounded shadow p-2 overflow-auto" style="height: 50vh;"></div>
        <form id="chat-form" class="flex-grow-1">
            <label for=""model-select" class="form-label">Model</label>
            <select class="form-control" id="model-select" name="model">
                <?php foreach ($data['models'] as $model): ?>
                    <option value="<?= htmlspecialchars($model['name']) ?>">
                        <?= htmlspecialchars($model['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br/>
            <label for="prompt-input" class="form-label">Prompt</label>
            <textarea class="form-control" id="prompt-input" name="prompt" rows="4" placeholder="Enter your prompt here"></textarea>
            <br/>
            <button type="submit" class="btn btn-primary w-100">Send</button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/markdown-it/13.0.1/markdown-it.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex/dist/katex.min.js"></script>
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
        });

        const renderLatex = (text) => {
            return text
                .replace(/\\\[(.+?)\\\]/gs, (_, math) =>
                    katex.renderToString(math.trim(), { displayMode: true })
                )
                .replace(/\\\((.+?)\\\)/g, (_, math) =>
                    katex.renderToString(math.trim(), { displayMode: false })
                )
                .replace(/\$\$(.+?)\$\$/gs, (_, math) =>
                    katex.renderToString(math.trim(), { displayMode: true })
                )
                .replace(/\$(.+?)\$/g, (_, math) =>
                    katex.renderToString(math.trim(), { displayMode: false })
                );
        };

        const processContent = (content) => {
            return md.render(renderLatex(content));
        };

        document.getElementById('chat-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const promptInput = document.getElementById('prompt-input');
            const chatHistory = document.getElementById('chat-history');
            const modelSelect = document.getElementById('model-select');
            const prompt = promptInput.value.trim();
            
            if (!prompt) return;

            const userMessageEl = document.createElement('div');
            userMessageEl.classList.add('chat-message', 'user-message');
            userMessageEl.innerHTML = `<strong>You (${modelSelect.value}):</strong> ${prompt}`;
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
                    // Remove CoT button content if it exists (TODO: refactor this, it's a crappy way to do it)
                    const cotContainer = el.querySelector('.cot-container');
                    if (cotContainer) {
                        el.removeChild(cotContainer);
                    }

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
                aiMessageEl.classList.add('chat-message', 'ai-message');

                // Create COT button if chain of thought exists
                let cotButton = '';

                // temp disabled to create a fucking style for it
                // if (data.chainOfThought.trim() && data.chainOfThought !== '<br>\n<br>') {
                //     cotButton = `
                //         <div class="cot-container">
                //             <button class="cot-button">🤔</button>
                //             <div class="cot-tooltip">
                //                 <strong>Chain of Thought:</strong>
                //                 <p>${data.chainOfThought}</p>
                //             </div>
                //         </div>
                //     `;
                // }

                const markedContent = md.render(data.response);
                aiMessageEl.innerHTML = `<strong>AI (${modelSelect.value}):</strong> 
                    ${cotButton}
                    <div class="message-content">${processContent(data.response)}</div>`;

                chatHistory.appendChild(aiMessageEl);
                chatHistory.scrollTop = chatHistory.scrollHeight;
            })
            .catch(error => {
                if (loadingEl.parentNode) {
                    chatHistory.removeChild(loadingEl);
                }

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