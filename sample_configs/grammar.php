<?php

return [
	'rules' => [
			'T_DOCUMENT'	=>[
				['T_STATEMENT','T_STATEMENT|optional']
			],
			'T_STATEMENT'	=>[
				['T_INTERJECTION','T_PUNCTUATION','T_CONJUNCTION'],
				['T_NOUN_PHRASE','T_MODALITY|optional','T_VERB_PHRASE', 'T_PUNCTUATION'],
				// ['T_VERB_PHRASE','T_PUNCTUATION'],
			],
			'T_NOUN_PHRASE'	=>[
				[['T_INDICATOR|optional','T_DETERMINER|optional'],'T_DESCRIPTOR|optional',['T_ENTITY|optional','T_SYMBOL|optional','T_ALIAS|optional'],'T_PREP_PHRASE|optional',['T_PUNCTUATION|optional','T_CONJUNCTION|optional'],'T_VERB_PHRASE|optional']
			],
			'T_VERB_PHRASE'	=>[
				['T_OPERATOR','T_NOUN_PHRASE|optional',['T_PUNCTUATION|optional','T_CONJUNCTION|optional'],'T_MODIFIER|optional','T_NOUN_PHRASE|optional']
			],
			'T_PREP_PHRASE'	=>[
				['T_DIRECTOR', 'T_NOUN_PHRASE|optional', 'T_VERB_PHRASE|optional']
			],
		],
	'commands' => [
			'T_OPERATOR'	=>[
				'expects'=>['T_WHITESPACE', 'T_DETERMINER', 'T_DESCRIPTOR','T_ENTITY','T_ALIAS','T_INDICATOR','T_DIRECTOR','T_MODIFIER','T_PUNCTUATION']
			],
			'T_DESCRIPTOR'	=>[
				'expects'=>['T_WHITESPACE|T_SUFFIX','T_ENTITY','T_SYMBOL','T_PUNCTUATION']],
			'T_ENTITY'		=>[
				'expects'=>['T_WHITESPACE|T_SUFFIX','T_ENTITY','T_ALIAS','T_INDICATOR','T_DIRECTOR','T_OPERATOR','T_PUNCTUATION','T_CONJUNCTION']],
			'T_NEGATION'		=>[
				'expects'=>['T_WHITESPACE','T_ENTITY','T_ALIAS','T_INDICATOR','T_DIRECTOR','T_OPERATOR','T_PUNCTUATION','T_CONJUNCTION']],
			'T_SUFFIX'		=>[
				'expects'=>['C_PREVIOUS']],
			'T_ALIAS'		=>[
				'expects'=>['T_WHITESPACE','T_INDICATOR','T_DIRECTOR','T_OPERATOR','T_PUNCTUATION','T_CONJUNCTION']],
			'T_SYMBOL'		=>[
				'expects'=>['T_WHITESPACE','T_ENTITY','T_ALIAS','T_INDICATOR','T_DIRECTOR','T_OPERATOR','T_PUNCTUATION','T_CONJUNCTION']],
			'T_CONJUNCTION'	=>[
				'expects'=>['T_WHITESPACE','T_ENTITY','T_SYMBOL','T_ALIAS','T_INDICATOR','T_DIRECTOR','T_OPERATOR']],
			'T_INDICATOR'	=>[
				'expects'=>['T_WHITESPACE','T_ENTITY','T_SYMBOL','T_ALIAS','T_DESCRIPTOR']],
			'T_DETERMINER'	=>[
				'expects'=>['T_WHITESPACE','T_ENTITY','T_SYMBOL','T_ALIAS','T_DESCRIPTOR']],
			'T_WHITESPACE'	=>[
				'expects'=>['C_PREVIOUS'],
				'excludes'=>['T_PUNCTUATION']],
			'T_MODIFIER'	=>[
				'expects'=>['T_WHITESPACE','T_OPERATOR','T_PUNCTUATION']],
			'T_PUNCTUATION'	=>[
				'expects'=>['T_WHITESPACE','T_PUNCTUATION']],
			'T_DIRECTOR'	=>[
				'expects'=>['T_WHITESPACE','T_DESCRIPTOR','T_ENTITY','T_SYMBOL','T_ALIAS','T_INDICATOR','T_PUNCTUATION']],
			'T_INTERJECTION'=>[
				'expects'=>['T_WHITESPACE','T_PUNCTUATION']],
		],
];