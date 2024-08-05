<?php

namespace SynthetIQ;

use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Classification\SVC;
use Phpml\SupportVectorMachine\Kernel;
use Phpml\ModelManager;
use Phpml\Dataset\ArrayDataset;

class LearningModel {
    protected $tokenizer;
    protected $tfIdfTransformer;
    protected $classifier;
    protected $modelManager;
    protected $dataset;

    public function __construct() {
        $this->tokenizer = new WhitespaceTokenizer();
        $this->tfIdfTransformer = new TfIdfTransformer();
        $this->classifier = new SVC(Kernel::RBF);
        $this->modelManager = new ModelManager();
        $this->dataset = new ArrayDataset([], []);
    }

    public function train(array $interactions) {
        $samples = [];
        $labels = [];

        foreach ($interactions as $interaction) {
            $tokens = $this->tokenizer->tokenize($interaction['input']);
            $samples[] = implode(' ', $tokens);
            $labels[] = $interaction['output'];
        }

        $this->tfIdfTransformer->fit($samples);
        $this->tfIdfTransformer->transform($samples);

        $this->classifier->train($samples, $labels);

        $this->modelManager->saveToFile($this->classifier, 'model.dat');
    }

    public function generate($input, $parsedData = null, $entities = null) {
        $tokens = $this->tokenizer->tokenize($input);
        $this->tfIdfTransformer->transform($tokens);

        $prediction = $this->classifier->predict([implode(' ', $tokens)]);

        return $prediction;
    }

    public function loadModel($filePath) {
        $this->classifier = $this->modelManager->restoreFromFile($filePath);
    }
}
