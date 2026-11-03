<?php
/** Apply owner-defined whitespace rules without rewriting behavior or vendor files. */
$finder = PhpCsFixer\Finder::create()
	->in( __DIR__ )
	->exclude( array( 'vendor', 'node_modules', 'dist', '.git' ) )
	->name( '*.php' );

return ( new PhpCsFixer\Config() )
	->setIndent( "\t" )
	->setLineEnding( "\n" )
	->setRiskyAllowed( false )
	->setUsingCache( false )
	->setRules( array(
		'indentation_type' => true,
		'statement_indentation' => true,
		'no_multiple_statements_per_line' => true,
		'array_indentation' => true,
		'phpdoc_indent' => true,
		'line_ending' => true,
		'no_trailing_whitespace' => true,
		'no_trailing_whitespace_in_comment' => true,
		'control_structure_braces' => true,
		'braces_position' => array(
			'classes_opening_brace' => 'same_line',
			'functions_opening_brace' => 'same_line',
			'allow_single_line_anonymous_functions' => false,
		),
		'single_blank_line_at_eof' => true,
	) )
	->setFinder( $finder );
