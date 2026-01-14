<?php
use BlueFission\SynthetIQ\SynthetIQ;
use BlueFission\Automata\Language\{
    Interpreter,
    Grammar,
    StemmerLemmatizer,
    Documenter,
    Walker
};
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;

require 'vendor/autoload.php';

require 'sample_configs/skills.php';

$dialogue = require 'sample_configs/dialogue.php';
$intentBoosts = require 'sample_configs/intent_boosts.php';
$grammar = require 'sample_configs/grammar.php';
$tokens = require 'sample_configs/tokens.php';
$documenter = require 'sample_configs/documenter.php';

$modelDir = __DIR__ . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'ml' . DIRECTORY_SEPARATOR;
if (!is_dir($modelDir)) {
    mkdir($modelDir, 0777, true);
}

$interpreter = new Interpreter(
    new Grammar( 
        new StemmerLemmatizer(),
        $grammar['rules'],
        $grammar['commands'],
        $tokens
    ), 
    $documenter,
    new Walker()
);

$analyzer = new KeywordTopicAnalyzer(new NaiveBayesTextClassification, $modelDir);

$ai = new SynthetIQ( $interpreter, $analyzer );
// Default fallback if selection yields no response.
$fallbackResponse = "I'm not sure how to respond to that yet.";

function normalizeKeywords(array $keywords, array $exclude = []): array
{
    $excludeSet = [];
    foreach ($exclude as $value) {
        $excludeSet[strtolower(trim((string)$value))] = true;
    }

    $normalized = [];
    foreach ($keywords as $keyword) {
        $keyword = strtolower(trim((string)$keyword));
        if ($keyword === '' || isset($excludeSet[$keyword])) {
            continue;
        }
        $normalized[$keyword] = true;
    }

    return array_keys($normalized);
}

function trainRoutes(SynthetIQ $ai, array $dialogue, array $intentBoosts = []): void
{
    $stopwords = ['how', 'what', 'is', 'the', 'a', 'an', 'to', 'for', 'on', 'in'];
    $total = 0;
    foreach ($dialogue as $info) {
        $total += count($info[1]);
    }

    $current = 0;
    foreach ($dialogue as $category => $info) {
        $boost = $intentBoosts[$category] ?? [];
        $keywords = $info[2] ?? [];
        if (!empty($boost['keywords'])) {
            $keywords = array_merge($keywords, $boost['keywords']);
        }
        $exclude = $boost['exclude'] ?? [];
        $keywords = normalizeKeywords($keywords, array_merge($stopwords, $exclude));
        $priorityBase = $boost['priority'] ?? null;
        $ai->addIntentKeywords($category, $keywords, $priorityBase);

        foreach ($info[1] as $statement) {
            $ai->addRoute($statement, $category, $info[0]);
            $current++;
            if ($current % 50 === 0 || $current === $total) {
                $percent = (int)round(($current / $total) * 100);
                echo "\rTraining: {$current}/{$total} ({$percent}%)";
                if (function_exists('flush')) {
                    flush();
                }
            }
        }
    }
    echo "\rTraining: {$total}/{$total} (100%)\n";
}

trainRoutes($ai, $dialogue, $intentBoosts);

// if is running on command line
if ( php_sapi_name() === 'cli' ) {
    $args = array_slice($argv, 1);
    if (!empty($args)) {
        $userMessage = trim(implode(' ', $args));
        $response = $ai->processInput($userMessage);
        if ($response === '') {
            $response = $fallbackResponse;
        }
        echo "You: {$userMessage}\n";
        echo "AI: {$response}\n";
        exit;
    }

    echo "SynthetIQ Chat System (type 'exit' to quit)\n";
    echo "----------------------------------\n";

    $handle = fopen("php://stdin", "r");
    while (true) {
        echo "You: ";
        $userMessage = trim(fgets($handle) ?: '');

        if ($userMessage === '') {
            continue;
        }

        if (strtolower($userMessage) === 'exit') {
            echo "Goodbye!\n";
            break;
        }

        try {
            $response = $ai->processInput($userMessage);
        } catch (Exception $e) {
            $response = $e->getMessage();
        }

        if ($response === '') {
            $response = $fallbackResponse;
        }

        echo "AI: " . $response . PHP_EOL;
    }

    fclose($handle);
    exit;
}

// if is post request
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = $input['message'] ?? '';
    if (!is_string($userMessage) || trim($userMessage) === '') {
        http_response_code(400);
        echo json_encode(['response' => $fallbackResponse]);
        exit;
    }

    $response = $ai->processInput($userMessage);
    if ($response === '') {
        $response = $fallbackResponse;
    }
    echo json_encode(['response' => $response]);

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .chat-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }
        .messages {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        .message {
            padding: 10px;
            border-bottom: 1px solid #eaeaea;
        }
        .message.user {
            text-align: right;
            background-color: #d4f7dc;
        }
        .message.ai {
            text-align: left;
            background-color: #f7f7d4;
        }
        .message:last-child {
            border-bottom: none;
        }
        form {
            display: flex;
        }
        input[type="text"] {
            flex: 1;
            padding: 10px;
            border: 1px solid #eaeaea;
            border-radius: 5px;
        }
        button {
            padding: 10px 20px;
            border: none;
            background-color: #007bff;
            color: #fff;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="messages" id="messages"></div>
        <form id="chatForm">
            <input type="text" id="userMessage" placeholder="Type your message here..." autocomplete="off">
            <button type="submit">Send</button>
        </form>
    </div>

    <script>
        document.getElementById('chatForm').addEventListener('submit', function(event) {
            event.preventDefault();
            let userMessage = document.getElementById('userMessage').value;
            if (userMessage.trim() !== '') {
                addMessage('user', userMessage);
                document.getElementById('userMessage').value = '';

                fetch('example.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message: userMessage })
                })
                .then(response => response.json())
                .then(data => addMessage('ai', data.response))
                .catch(error => console.error('Error:', error));
            }
        });

        function addMessage(sender, text) {
            let messageDiv = document.createElement('div');
            messageDiv.classList.add('message', sender);
            messageDiv.textContent = text;
            document.getElementById('messages').appendChild(messageDiv);
            document.getElementById('messages').scrollTop = document.getElementById('messages').scrollHeight;
        }
    </script>
</body>
</html>
