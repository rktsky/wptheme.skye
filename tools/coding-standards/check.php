<?php
/** Check first-party PHP architecture and documented contracts without running a site. */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit;
}

require __DIR__ . '/vendor/autoload.php';
$root = realpath( $argv[1] ?? dirname( __DIR__, 2 ) );
if ( ! $root || ! is_dir( $root ) ) {
	fwrite( STDERR, "Expected an existing project root.\n" );
	exit( 2 );
}
$parser = ( new PhpParser\ParserFactory() )->createForHostVersion();
$finder = new PhpParser\NodeFinder();
$errors = array();
$count = 0;

/**
 * Recover explicit outer context keys without mistaking nested data for inputs.
 *
 * Dynamic contexts remain a review responsibility rather than a false success.
 *
 * @since 3.0.0
 * @param PhpParser\Node\Expr $expression Supplied component context expression.
 * @return string[] Statically known outer string keys.
 */
function skye_check_context_keys( $expression ) {
	$keys = array();
	if ( $expression instanceof PhpParser\Node\Expr\Array_ ) {
		foreach ( $expression->items as $item ) {
			if ( $item && $item->key instanceof PhpParser\Node\Scalar\String_ ) {
				$keys[] = $item->key->value;
			}
		}
	} elseif ( $expression instanceof PhpParser\Node\Expr\FuncCall && $expression->name instanceof PhpParser\Node\Name && in_array( strtolower( $expression->name->toString() ), array( 'array_filter', 'array_merge' ), true ) ) {
		foreach ( $expression->args as $argument ) {
			$keys = array_merge( $keys, skye_check_context_keys( $argument->value ) );
		}
	}
	return $keys;
}

/**
 * Require complete typed documentation for each actual declared parameter.
 *
 * Documentation meaning and historical accuracy still need review; a tag alone
 * cannot prove the rationale, security contract or truth of the introduction date.
 *
 * @since 3.0.0
 * @param PhpParser\Node\FunctionLike $function Parsed first-party function or method.
 * @return string[] Missing or incomplete documentation requirements.
 */
function skye_check_docblock( $function ) {
	$comment = $function->getDocComment();
	if ( ! $comment ) {
		return array( 'English purpose/rationale DocBlock is required' );
	}
	$text = $comment->getText();
	$issues = array();
	foreach ( array( 'return', 'since' ) as $tag ) {
		if ( ! preg_match( '/@' . $tag . '\s+\S+/', $text ) ) {
			$issues[] = 'typed @return and evidenced @since are required';
			break;
		}
	}
	foreach ( $function->getParams() as $parameter ) {
		if ( is_string( $parameter->var->name ) && ! preg_match( '/@param\s+\S+\s+(?:&?\.\.\.)?\$' . preg_quote( $parameter->var->name, '/' ) . '\b/', $text ) ) {
			$issues[] = 'typed @param $' . $parameter->var->name . ' is required';
		}
	}
	return $issues;
}

$paths = array();
foreach ( array( 'lib', 'components', 'views', 'templates', 'tests', 'tools' ) as $directory ) {
	if ( ! is_dir( $root . '/' . $directory ) ) {
		continue;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$relative = substr( $file->getPathname(), strlen( $root ) + 1 );
		if ( 'php' === $file->getExtension() && ! str_contains( $relative, '/vendor/' ) ) {
			$paths[ $relative ] = $file->getPathname();
		}
	}
}
foreach ( array( 'functions.php', 'base.php', 'index.php', '.php-cs-fixer.dist.php' ) as $entry ) {
	if ( is_file( $root . '/' . $entry ) ) {
		$paths[ $entry ] = $root . '/' . $entry;
	}
}
ksort( $paths );
foreach ( $paths as $relative => $path ) {
	$source = file_get_contents( $path );
	$count++;
	try {
		$tree = $parser->parse( $source );
	} catch ( PhpParser\Error $error ) {
		$errors[] = $relative . ': ' . $error->getMessage();
		continue;
	}
	if ( ! preg_match( '/^<\?php\s*\/\*\*\s*\S/', $source ) ) {
		$errors[] = $relative . ': file purpose header is required';
	}
	$template = (bool) preg_match( '~^(?:components|views|templates)/~', $relative ) || 'base.php' === $relative;
	$production = ! str_starts_with( $relative, 'tests/' ) && ! str_starts_with( $relative, 'tools/' );
	foreach ( $finder->findInstanceOf( $tree, PhpParser\Node\FunctionLike::class ) as $function ) {
		foreach ( skye_check_docblock( $function ) as $issue ) {
			$errors[] = $relative . ':' . $function->getStartLine() . ': ' . $issue;
		}
		if ( $template ) {
			$errors[] = $relative . ':' . $function->getStartLine() . ': declarations belong in lib/';
		}
	}
	foreach ( $finder->findInstanceOf( $tree, PhpParser\Node\Stmt\ClassLike::class ) as $class ) {
		if ( $template ) {
			$errors[] = $relative . ':' . $class->getStartLine() . ': classes belong in lib/';
		}
	}
	foreach ( $finder->findInstanceOf( $tree, PhpParser\Node\Expr\Include_::class ) as $include ) {
		if ( $template ) {
			$errors[] = $relative . ':' . $include->getStartLine() . ': templates use get_component() instead of direct includes';
		}
	}
	foreach ( $finder->findInstanceOf( $tree, PhpParser\Node\Expr\FuncCall::class ) as $call ) {
		if ( ! $call->name instanceof PhpParser\Node\Name ) {
			continue;
		}
		$name = strtolower( $call->name->toString() );
		if ( $template && preg_match( '/^(?:get_posts|get_terms|get_users|update_field|update_option|update_post_meta|delete_post_meta|delete_transient|set_transient|wp_insert_post|wp_delete_post|wp_update_post|wp_send_json|wp_safe_redirect|setcookie|get_defined_vars)$/', $name ) ) {
			$errors[] = $relative . ':' . $call->getStartLine() . ': ' . $name . ' belongs in lib/';
		}
		if ( $production && 'get_defined_vars' === $name ) {
			$errors[] = $relative . ':' . $call->getStartLine() . ': pass explicit context instead of caller scope';
		}
		if ( in_array( $name, array( 'get_component', 'skye_component_html' ), true ) && isset( $call->args[2] ) ) {
			foreach ( array_intersect( skye_check_context_keys( $call->args[2]->value ), array( 'name', 'second', 'args', 'template' ) ) as $key ) {
				$errors[] = $relative . ':' . $call->getStartLine() . ': reserved component context key ' . $key;
			}
		}
	}
	if ( $template ) {
		foreach ( $finder->findInstanceOf( $tree, PhpParser\Node\Expr\New_::class ) as $new ) {
			if ( $new->class instanceof PhpParser\Node\Name && 'wp_query' === strtolower( $new->class->toString() ) ) {
				$errors[] = $relative . ':' . $new->getStartLine() . ': queries belong in lib/';
			}
		}
		if ( preg_match( '~<(?:style|script)\b|\sstyle\s*=~i', preg_replace( '~<\?php.*?\?>~s', '', $source ) ) ) {
			$errors[] = $relative . ': inline styling or behavior belongs in assets/';
		}
	} elseif ( $production ) {
		foreach ( $finder->findInstanceOf( $tree, PhpParser\Node\Stmt\InlineHTML::class ) as $html ) {
			if ( '' !== trim( $html->value ) ) {
				$errors[] = $relative . ':' . $html->getStartLine() . ': HTML layout belongs in components/';
			}
		}
	}
	if ( $production ) {
		foreach ( $finder->findInstanceOf( $tree, PhpParser\Node\Stmt\Echo_::class ) as $echo ) {
			foreach ( $finder->findInstanceOf( $echo->exprs, PhpParser\Node\Scalar\String_::class ) as $literal ) {
				if ( preg_match( '~<\/?[a-zA-Z][\w:-]*(?:\s|>|/)~', $literal->value ) ) {
					$errors[] = $relative . ':' . $echo->getStartLine() . ': write layout as HTML in a component';
				}
			}
		}
	}
}
foreach ( array_unique( $errors ) as $error ) {
	fwrite( STDERR, $error . "\n" );
}
echo 'Architecture/contracts: ' . $count . ' files, ' . count( array_unique( $errors ) ) . " issues.\n";
exit( $errors ? 1 : 0 );
