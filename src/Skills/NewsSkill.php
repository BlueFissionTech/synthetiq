<?php
namespace BlueFission\SynthetIQ\Skills;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Skill\BaseSkill;
use App\Business\Services\WikiNewsService;

class NewsSkill extends BaseSkill
{
    protected $response;

    public function __construct()
    {
        parent::__construct('News Skill');
    }

    public function execute(Context $context = null)
    {
        $topic = $context->get('topic') ?? 'Technology';
        $location = $context->get('location');
        $news = new WikiNewsService();
        $loc = instance('location');

        if (empty($location)) {
            $location = $loc->getIpLocation();
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
