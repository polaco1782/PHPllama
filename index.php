<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

class OllamaUI {
    private $ollamaUrl = 'http://localhost:11434/api';
    private $selectedModel = '';
    private $chatHistory = [];

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
        
        $messages = array_map(function($msg) {
            return [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }, $history);
        
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];

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
        
        // Remove chain of thought from response
        $cleanResponse = preg_replace('/<think>.*?<\/think>/s', '', $fullResponse);
        
        return [
            'response' => trim($cleanResponse),
            'chainOfThought' => trim($chainOfThought)
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
}

$ui = new OllamaUI();
$data = $ui->handleRequest();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHPllama Chat Web UI</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>PHPllama Chat Web UI</h1>
        <div class="chat-interface">
            <div id="chat-history" class="chat-history"></div>
            
            <form id="chat-form">
                <select id="model-select" name="model">
                    <?php foreach ($data['models'] as $model): ?>
                        <option value="<?= htmlspecialchars($model['name']) ?>">
                            <?= htmlspecialchars($model['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <textarea id="prompt-input" name="prompt" rows="4" placeholder="Enter your prompt here"></textarea>
                <button type="submit">Send</button>
            </form>
        </div>
    </div>

    <script>
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
                const role = el.classList.contains('user-message') ? 'user' : 'assistant';
                const content = el.textContent.replace(/^You \(.*?\): |^AI \(.*?\): /, '');
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
            if (data.chainOfThought) {
                cotButton = `
                    <div class="cot-button">🤔
                        <div class="cot-tooltip">
                            <strong>Chain of Thought:</strong>
                            <p>${data.chainOfThought}</p>
                        </div>
                    </div>
                `;
            }

            aiMessageEl.innerHTML = `
                <strong>AI (${modelSelect.value}):</strong> 
                ${data.response}
                ${cotButton}
            `;
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