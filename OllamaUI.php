<?php

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