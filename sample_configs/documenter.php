<?php

use BlueFission\Automata\Language\Documenter;

$documenter = new Documenter();

$documenter->addRule('T_ENTITY', function( $cmd, &$statement ) {
	$this->prepare_entity();
	$statement->{$this->_entity} = $cmd['match'];
});

return $documenter;