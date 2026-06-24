<?php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\SynthetIQ\Clients\LocationClientInterface;
use BlueFission\SynthetIQ\Clients\NewsClientInterface;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;

class NewsSkill extends BaseSkill
{
    protected $response;
    protected $news_client;
    protected $location_client;

    public function __construct(?NewsClientInterface $newsClient = null, ?LocationClientInterface $locationClient = null)
    {
        parent::__construct('News Skill');
        $this->news_client = $newsClient;
        $this->location_client = $locationClient;
    }

    public function execute(Context $context = null)
    {
        $topic = $context ? (string)($context->get('topic') ?? 'Technology') : 'Technology';
        $location = $context ? (string)($context->get('location') ?? '') : '';
        $news = $this->news_client;
        $loc = $this->location_client;

        if (!$news) {
            $this->response = [];
            return $this->response;
        }

        if (Val::isEmpty($location)) {
            $location = $loc ? $loc->getIpLocation() : '';
        }

        $this->response = $news->getHeadlines($topic, $location);
        return $this->response;
    }

    public function response(): string
    {
        if (!Arr::is($this->response) || Val::isEmpty($this->response)) {
            return "No news found.";
        }

        $headlines = '';
        foreach ($this->response as $headline) {
            $headlines = Val::isEmpty($headlines)
                ? (string)$headline
                : Str::make($headlines)->append("\n")->append((string)$headline)->val();
        }

        return "Here are the latest news headlines:\n\n{$headlines}";
    }
}
