<?php

use BlueFission\Automata\Language\Documenter;

$documenter = new Documenter();

// Define rules for handling different types of tokens
// Operators usually define the action (verb) in the sentence
$documenter->addRule(['T_OPERATOR'], function($cmd, $statement) {
    $statement->field('behavior', $cmd['match']);
});

// Entities can be subject, object, or indirect object based on context
$documenter->addRule(['T_ENTITY'], function($cmd, $statement) {
    if (!$statement->field('subject')) {
        $statement->field('subject', $cmd['match']);
    } elseif ($statement->field('behavior') && !$statement->field('object')) {
        $statement->field('object', $cmd['match']);
    } elseif ($statement->field('object') && $statement->field('relationship')) {
        // Indirect object following a preposition
        $statement->field('indirect_object', $cmd['match']);
    }
});

// Conjunctions might indicate compound subjects or objects
$documenter->addRule(['T_CONJUNCTION'], function($cmd, $statement) {
    if ($cmd['match'] === 'and' || $cmd['match'] === 'or') {
        // Handle compound subjects or objects
        $this->prepare_entity(); // Decide based on context if it's subject or object
        $entityType = $this->get_entity_type(); // 'subject' or 'object'
        if (is_array($statement->field($entityType))) {
            array_push($statement->field($entityType), $this->retrieve()); // Add the previous buffer entity
        } else {
            $statement->field($entityType, [$statement->field($entityType), $this->retrieve()]);
        }
    }
});

// Directors determine the relationship or position of entities
$documenter->addRule(['T_DIRECTOR'], function($cmd, $statement) {
    $statement->field('relationship', $cmd['match']);
});

// Utilize buffer for ambiguous entity roles until more information is available
$documenter->addRule(['T_ENTITY'], function($cmd, $statement) {
    $this->store($cmd['match']); // Temporarily store entity
}, 1); // Lower priority to handle after main entity rule

// Register an error handler or a rule for unexpected tokens
$documenter->addRule(['T_UNKNOWN'], function($cmd, $statement) {
    throw new Exception("Unhandled token: {$cmd['match']}");
});

return $documenter;