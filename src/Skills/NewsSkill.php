<?php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use BlueFission\SynthetIQ\Clients\LocationClientInterface;
use BlueFission\SynthetIQ\Clients\NewsClientInterface;

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
        $topic = $context->get('topic') ?? 'Technology';
        $location = $context->get('location');
        $news = $this->news_client;
        $loc = $this->location_client;

        if (!$news) {
            $this->response = [];
            return $this->response;
        }

        if (empty($location)) {
            $location = $loc ? $loc->getIpLocation() : '';
        }

        $this->response = $news->getHeadlines($topic, $location);
        return $this->response;
    }

    public function response(): string
    {
        if (empty($this->response)) {
            return "No news found.";
        }

        $headlines = implode("\n", $this->response);
        return "Here are the latest news headlines:\n\n{$headlines}";
    }
}
