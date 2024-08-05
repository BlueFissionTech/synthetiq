<?php
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Matcher;
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use BlueFission\SynthetIQ\Skills\GreetingResponseSkill;
use BlueFission\SynthetIQ\Skills\GoodbyeResponseSkill;
use BlueFission\SynthetIQ\Skills\StatusSkill;
use BlueFission\SynthetIQ\Skills\HowAreYouResponseSkill;
use BlueFission\SynthetIQ\Skills\TimeAndDateSkill;
use BlueFission\SynthetIQ\Skills\WeatherSkill;
use BlueFission\SynthetIQ\Skills\NewsSkill;

$matcher = new Matcher( new KeywordTopicAnalyzer( new NaiveBayesTextClassification, 'model.ml') );

$greetingIntent = new Intent('greeting.response', 'Greeting Response', [
    'keywords' => [
        ['word' => 'good morning', 'priority' => 3.0],
        ['word' => 'good afternoon', 'priority' => 3.0],
        ['word' => 'good evening', 'priority' => 3.0],
        ['word' => 'hello', 'priority' => 4.0],
        ['word' => 'hi', 'priority' => 4.0],
    ],
]);

$greetingSkill = new GreetingResponseSkill();

$matcher
    ->registerSkill($greetingSkill)
    ->registerIntent($greetingIntent)
    ->associate($greetingIntent, $greetingSkill);


// New intent and skill registration for GoodbyeResponseSkill
$goodbyeIntent = new Intent('goodbye.response', 'Goodbye Response', [
    'keywords' => [
        ['word' => 'good night', 'priority' => 4.0],
        ['word' => 'goodbye', 'priority' => 4.0],
        ['word' => 'bye', 'priority' => 4.0],
        ['word' => 'see you later', 'priority' => 3.0],
        ['word' => 'take care', 'priority' => 3.0],
    ],
]);

$goodbyeSkill = new GoodbyeResponseSkill();

$matcher
    ->registerSkill($goodbyeSkill)
    ->registerIntent($goodbyeIntent)
    ->associate($goodbyeIntent, $goodbyeSkill);

// New intent and skill registration for HowAreYouResponseSkill
$howAreYouIntent = new Intent('howareyou.response', 'How Are You Response', [
    'keywords' => [
        ['word' => 'how are you', 'priority' => 5.0],
        ['word' => 'how is it going', 'priority' => 4.0],
        ['word' => 'how are things', 'priority' => 3.0],
        ['word' => 'how do you feel', 'priority' => 2.0],
        ['word' => 'are you okay', 'priority' => 2.0],
    ],
]);

$howAreYouSkill = new HowAreYouResponseSkill();

$matcher
    ->registerSkill($howAreYouSkill)
    ->registerIntent($howAreYouIntent)
    ->associate($howAreYouIntent, $howAreYouSkill);


// New intent and skill registration for UpdateSkill
$updateIntent = new Intent('status.skill', 'Update Skill', [
    'keywords' => [
        ['word' => 'what\'s up', 'priority' => 2.0],
        ['word' => 'what\'s going on', 'priority' => 2.0],
        ['word' => 'what\'s your status', 'priority' => 5.0],
        ['word' => 'give me an update', 'priority' => 3.0],
        ['word' => 'give me a status report', 'priority' => 3.0],
        ['word' => 'system status', 'priority' => 3.0],
    ],
]);

$updateSkill = new StatusSkill();

$matcher
    ->registerSkill($updateSkill)
    ->registerIntent($updateIntent)
    ->associate($updateIntent, $updateSkill);
// New intent and skill registration for TimeAndDateSkill
$timeAndDateIntent = new Intent('timeanddate.response', 'Time And Date Response', [
    'keywords' => [
        ['word' => 'what is the time', 'priority' => 1.0],
        ['word' => 'what time is it', 'priority' => 4.0],
        ['word' => 'tell me the time', 'priority' => 2.0],
        ['word' => 'current time', 'priority' => 3.0],
        ['word' => 'what is the date', 'priority' => 2.0],
        ['word' => 'what date is it', 'priority' => 2.0],
        ['word' => 'tell me the date', 'priority' => 1.0],
        ['word' => 'current date', 'priority' => 1.0],
    ],
]);

$timeAndDateSkill = new TimeAndDateSkill();

$matcher
    ->registerSkill($timeAndDateSkill)
    ->registerIntent($timeAndDateIntent)
    ->associate($timeAndDateIntent, $timeAndDateSkill);

// WeatherSkill
$weatherIntent = new Intent('weather.response', 'Weather Response', [
    'keywords' => [
        ['word' => 'weather', 'priority' => 3.0],
        ['word' => 'current weather', 'priority' => 3.0],
        ['word' => 'weather update', 'priority' => 2.0],
        ['word' => 'what is the weather', 'priority' => 2.0],
        ['word' => 'weather forecast', 'priority' => 1.0],
        ['word' => 'what is it like outside', 'priority' => 1.0],
        ['word' => 'what is the temperature', 'priority' => 5.0],
        ['word' => 'current temperature', 'priority' => 1.0],
        ['word' => 'what\'s the forecast', 'priority' => 1.0],
    ],
]);

// $weatherSkill = new WeatherSkill();
$matcher
    // ->registerSkill($weatherSkill)
    ->registerIntent($weatherIntent);
    // ->associate($weatherIntent, $weatherSkill);

// NewsSkill
$newsIntent = new Intent('news.response', 'News Response', [
    'keywords' => [
        ['word' => 'news', 'priority' => 5.0],
        ['word' => 'latest news', 'priority' => 1.0],
        ['word' => 'news update', 'priority' => 1.0],
        ['word' => 'headline news', 'priority' => 1.0],
        ['word' => 'what is the news', 'priority' => 3.0],
        ['word' => 'what\'s the news', 'priority' => 1.0],
        ['word' => 'what\'s the latest', 'priority' => 1.0],
        ['word' => 'what\'s happening', 'priority' => 1.0],
        ['word' => 'what\'s new', 'priority' => 1.0],
        ['word' => 'what\'s trending', 'priority' => 1.0],
    ],
]);

// $newsSkill = new NewsSkill();
$matcher
    // ->registerSkill($newsSkill)
    ->registerIntent($newsIntent);
    // ->associate($newsIntent, $newsSkill);

// ReminderSkill
$reminderIntent = new Intent('reminder.response', 'Reminder Response', [
    'keywords' => [
        ['word' => 'set a reminder', 'priority' => 4.0],
        ['word' => 'remind me', 'priority' => 4.0],
        ['word' => 'create a reminder', 'priority' => 3.0],
    ],
]);

// $reminderSkill = new ReminderSkill();
$matcher->registerIntent($reminderIntent);

// TimerSkill
$timerIntent = new Intent('timer.response', 'Timer Response', [
    'keywords' => [
        ['word' => 'set a timer', 'priority' => 4.0],
        ['word' => 'start a timer', 'priority' => 4.0],
        ['word' => 'timer', 'priority' => 3.0],
    ],
]);

// $timerSkill = new TimerSkill();
$matcher->registerIntent($timerIntent);

// DataCollectionSkill
$dataCollectionIntent = new Intent('data.collection', 'Data Collection', [
    'keywords' => [
        ['word' => 'collect data', 'priority' => 4.0],
        ['word' => 'data scraping', 'priority' => 4.0],
        ['word' => 'gather data', 'priority' => 3.0],
    ],
]);

// $dataCollectionSkill = new DataCollectionSkill();
$matcher->registerIntent($dataCollectionIntent);

// AggregatorSkill
$aggregatorIntent = new Intent('aggregator.response', 'Aggregator Response', [
    'keywords' => [
        ['word' => 'content aggregator', 'priority' => 4.0],
        ['word' => 'news aggregator', 'priority' => 4.0],
        ['word' => 'RSS feed', 'priority' => 3.0],
    ],
]);

// $aggregatorSkill = new AggregatorSkill();
$matcher->registerIntent($aggregatorIntent);

// CodeGenerationSkill
$codeGenerationIntent = new Intent('code.generation', 'Code Generation', [
    'keywords' => [
        ['word' => 'generate code', 'priority' => 5.0],
        ['word' => 'write code', 'priority' => 4.0],
        ['word' => 'code snippet', 'priority' => 3.0],
        ['word' => 'create code', 'priority' => 3.0],
        ['word' => 'auto code', 'priority' => 3.0],
    ],
]);

// $codeGenerationSkill = new CodeGenerationSkill();
$matcher->registerIntent($codeGenerationIntent);

// DataAnalysisSkill
$dataAnalysisIntent = new Intent('data.analysis', 'Data Analysis', [
    'keywords' => [
        ['word' => 'analyze data', 'priority' => 5.0],
        ['word' => 'data analysis', 'priority' => 4.0],
        ['word' => 'statistical analysis', 'priority' => 3.0],
        ['word' => 'data processing', 'priority' => 3.0],
        ['word' => 'data visualization', 'priority' => 3.0],
    ],
]);

// $dataAnalysisSkill = new DataAnalysisSkill();
$matcher->registerIntent($dataAnalysisIntent);

// FeatureEngineeringSkill
$featureEngineeringIntent = new Intent('feature.engineering', 'Feature Engineering', [
    'keywords' => [
        ['word' => 'feature engineering', 'priority' => 5.0],
        ['word' => 'create features', 'priority' => 4.0],
        ['word' => 'transform data', 'priority' => 3.0],
        ['word' => 'preprocess data', 'priority' => 3.0],
        ['word' => 'extract features', 'priority' => 3.0],
    ],
]);

// $featureEngineeringSkill = new FeatureEngineeringSkill();
$matcher->registerIntent($featureEngineeringIntent);

// OfficeAutomationSkill
$officeAutomationIntent = new Intent('office.automation', 'Office Automation', [
    'keywords' => [
        ['word' => 'office automation', 'priority' => 5.0],
        ['word' => 'automate tasks', 'priority' => 4.0],
        ['word' => 'document automation', 'priority' => 3.0],
        ['word' => 'spreadsheet automation', 'priority' => 3.0],
        ['word' => 'email automation', 'priority' => 3.0],
    ],
]);

// $officeAutomationSkill = new OfficeAutomationSkill();
$matcher->registerIntent($officeAutomationIntent);

$catchAllIntent = new Intent('catchall.response', 'Catch All Response', [
    'keywords' => [
        ['word' => 'what is your name', 'priority' => 1.0],
        ['word' => 'tell me a joke', 'priority' => 1.0],
        ['word' => 'do you like movies', 'priority' => 1.0],
        ['word' => 'what is your favorite color', 'priority' => 1.0],
        ['word' => 'how old are you', 'priority' => 1.0],
        ['word' => 'where do you live', 'priority' => 1.0],
        ['word' => 'can you cook', 'priority' => 1.0],
        ['word' => 'what do you do for fun', 'priority' => 1.0],
        ['word' => 'tell me something interesting', 'priority' => 1.0],
        ['word' => 'who created you', 'priority' => 1.0],
        ['word' => 'what languages do you speak', 'priority' => 1.0],
        ['word' => 'do you have any hobbies', 'priority' => 1.0],
        ['word' => 'what is your favorite food', 'priority' => 1.0],
        ['word' => 'tell me about yourself', 'priority' => 1.0],
        ['word' => 'do you have any pets', 'priority' => 1.0],
        ['word' => 'do you have any siblings', 'priority' => 1.0],
        ['word' => 'are you a robot', 'priority' => 1.0],
        ['word' => 'what is your favorite book', 'priority' => 1.0],
        ['word' => 'what is your favorite movie', 'priority' => 1.0],
        ['word' => 'do you like music', 'priority' => 1.0],
        ['word' => 'what are your dreams', 'priority' => 1.0],
        ['word' => 'what do you think about', 'priority' => 1.0],
        ['word' => 'what are you afraid of', 'priority' => 1.0],
        ['word' => 'do you have any secrets', 'priority' => 1.0],
        ['word' => 'what do you like most about yourself', 'priority' => 1.0],
    ],
]);

// $catchAllSkill = new CatchAllResponseSkill();
$matcher->registerIntent($catchAllIntent);
