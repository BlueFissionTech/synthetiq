<?php

declare(strict_types=1);

use BlueFission\Arr;
use BlueFission\Net\HTTP;
use BlueFission\Str;
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\Val;

require __DIR__ . '/examples/support.php';

$runtime = synthetiq_example_build(['fallback' => true]);
$ai = $runtime['ai'];

function synthetiq_example_turn(SynthetIQ $ai, string $message): array
{
    $message = Str::make($message)->trim()->val();
    if (Val::isEmpty($message)) {
        return [
            'response' => 'Please enter a message.',
            'intent' => ['label' => 'input.empty'],
            'fallback' => ['used' => true, 'reason' => 'empty_input'],
        ];
    }

    return $ai->processInputEnvelope($message);
}

if (PHP_SAPI === 'cli') {
    $args = Arr::slice($_SERVER['argv'] ?? [], 1);
    if (Val::isNotEmpty($args)) {
        $message = '';
        foreach ($args as $arg) {
            $message = Str::make($message)->append(' ')->append((string)$arg)->trim()->val();
        }

        $envelope = synthetiq_example_turn($ai, $message);
        echo "You: {$message}\n";
        echo 'AI: ' . (string)($envelope['response'] ?? '') . "\n";
        echo 'Intent: ' . (string)($envelope['intent']['label'] ?? 'unknown.intent') . "\n";
        exit(0);
    }

    echo "SynthetIQ Chat System (type 'exit' to quit)\n";
    echo "------------------------------------------\n";

    $handle = fopen('php://stdin', 'r');
    while ($handle) {
        echo 'You: ';
        $message = Str::make((string)fgets($handle))->trim()->val();

        if (Val::isEmpty($message)) {
            continue;
        }

        if (Str::match($message, 'exit', Str::IGNORE_CASE)) {
            echo "Goodbye!\n";
            break;
        }

        $envelope = synthetiq_example_turn($ai, $message);
        echo 'AI: ' . (string)($envelope['response'] ?? '') . PHP_EOL;
        echo 'Intent: ' . (string)($envelope['intent']['label'] ?? 'unknown.intent') . PHP_EOL;
    }

    if ($handle) {
        fclose($handle);
    }

    exit(0);
}

$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (Str::match($method, 'POST')) {
    header('Content-Type: application/json');

    $input = HTTP::jsonDecode((string)file_get_contents('php://input'), true, []);
    $message = Arr::is($input) ? (string)($input['message'] ?? '') : '';
    $envelope = synthetiq_example_turn($ai, $message);

    echo synthetiq_example_json([
        'response' => (string)($envelope['response'] ?? ''),
        'intent' => (string)($envelope['intent']['label'] ?? 'unknown.intent'),
        'fallback' => (bool)($envelope['fallback']['used'] ?? false),
    ]);

    exit(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SynthetIQ Example</title>
    <style>
        :root {
            color-scheme: light;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f6f7f9;
            color: #1f2933;
        }

        main {
            width: min(760px, calc(100% - 32px));
            margin: 0 auto;
            padding: 32px 0;
        }

        h1 {
            margin: 0 0 20px;
            font-size: 28px;
            font-weight: 650;
        }

        .chat {
            border: 1px solid #d7dde5;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .messages {
            min-height: 320px;
            max-height: 420px;
            overflow-y: auto;
            padding: 16px;
        }

        .message {
            margin-bottom: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            line-height: 1.45;
        }

        .message.user {
            margin-left: 18%;
            background: #d9f0e2;
        }

        .message.ai {
            margin-right: 18%;
            background: #eef1f5;
        }

        .message small {
            display: block;
            margin-top: 4px;
            color: #52616f;
            font-size: 12px;
        }

        form {
            display: flex;
            gap: 8px;
            padding: 12px;
            border-top: 1px solid #d7dde5;
            background: #fbfcfd;
        }

        input {
            flex: 1;
            min-width: 0;
            padding: 10px 12px;
            border: 1px solid #c7d0dc;
            border-radius: 6px;
            font: inherit;
        }

        button {
            padding: 10px 16px;
            border: 0;
            border-radius: 6px;
            background: #255f85;
            color: #fff;
            font: inherit;
            cursor: pointer;
        }
    </style>
</head>
<body>
<main>
    <h1>SynthetIQ Example</h1>
    <section class="chat">
        <div class="messages" id="messages"></div>
        <form id="chatForm">
            <input type="text" id="userMessage" placeholder="Type a message" autocomplete="off">
            <button type="submit">Send</button>
        </form>
    </section>
</main>
<script>
    const form = document.getElementById('chatForm');
    const input = document.getElementById('userMessage');
    const messages = document.getElementById('messages');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text) {
            return;
        }

        addMessage('user', text);
        input.value = '';

        const response = await fetch('example.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({message: text})
        });

        const data = await response.json();
        addMessage('ai', data.response, data.intent + (data.fallback ? ' fallback' : ''));
    });

    function addMessage(sender, text, meta = '') {
        const element = document.createElement('div');
        element.className = 'message ' + sender;
        element.textContent = text;
        if (meta) {
            const small = document.createElement('small');
            small.textContent = meta;
            element.appendChild(small);
        }
        messages.appendChild(element);
        messages.scrollTop = messages.scrollHeight;
    }
</script>
</body>
</html>
