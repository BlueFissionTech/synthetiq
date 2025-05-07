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
$grammar = require 'sample_configs/grammar.php';
$tokens = require 'sample_configs/tokens.php';
$documenter = require 'sample_configs/documenter.php';

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

$analyzer = new KeywordTopicAnalyzer( new NaiveBayesTextClassification, 'models/ml/');

$ai = new SynthetIQ( $interpreter, $analyzer );

foreach ($dialogue as $category=>$info) {
    foreach ($info[1] as $statement) {
        $ai->addRoute($statement, $category, $info[0]);
    }
}

echo "\n";

// if is running on command line
if ( php_sapi_name() === 'cli' ) {
    echo "SynthetIQ Chat System (type 'exit' to quit)\n";
    echo "----------------------------------\n";

    while (true) {
        echo "\033[34mYou:\033[0m ";
        $handle = fopen("php://stdin", "r");
        $userMessage = trim(fgets($handle));

        if (strtolower($userMessage) === 'exit') {
            echo "Goodbye!\n";
            break;
        }

        try {
            $response = $ai->processInput($userMessage);
        } catch (Exception $e) {
            $response = $e->getMessage();
        }

        echo "\033[32mAI:\033[0m " . $response . PHP_EOL;
    }

    return;
}

// if is post request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = $input['message'];

    $response = $ai->processInput($userMessage);
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
            <input type="text" id="userMessage" placeholder="Type your message here...">
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