<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'OllamaUI.php';

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
    <link rel="stylesheet" href="assets/style.css">
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
            <button type="submit" id="submit" class="btn btn-primary w-100">Send</button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/markdown-it@14.0.0/dist/markdown-it.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/markdown-it-mathjax@2.0.0/markdown-it-mathjax.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/script.js"></script>
</body>
</html>