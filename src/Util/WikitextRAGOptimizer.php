<?php

namespace MediaWiki\Extension\WikiRAG\Util;

use MediaWiki\Parser\MagicWordFactory;

/**
 * Convert wikitext to a more RAG-friendly format by removing unnecessary elements
 * This is not meant to be perfect and to cover every single edge case
 * but to convert common syntax which LLMs struggle with.
 */
class WikitextRAGOptimizer {

	/**
	 * @param MagicWordFactory $magicWordFactory
	 */
	public function __construct(
		private readonly MagicWordFactory $magicWordFactory
	) {
	}

	/**
	 * @param string $wikitext
	 * @return string
	 */
	public function process( string $wikitext ): string {
		$this->removeHtml( $wikitext );
		$this->removeMagicWords( $wikitext );
		$this->removeComments( $wikitext );
		$this->convertTags( $wikitext );
		$maskedTags = $this->maskTags( $wikitext );
		$this->convertTemplates( $wikitext );
		$this->convertTables( $wikitext );
		$this->splitSections( $wikitext );
		$this->unmaskTags( $wikitext, $maskedTags );
		$this->cleanUp( $wikitext );

		return $wikitext;
	}

	/**
	 * @param string &$wikitext
	 * @return void
	 */
	protected function removeHtml( string &$wikitext ): void {
		// Remove common HTML tags: div, span, p
		$wikitext = preg_replace( '#<\s*(div|span|p)[^>]*>(.*?)<\s*/\s*\1\s*>#is', '$2', $wikitext );
		// Remove br
		$wikitext = preg_replace( '#<\s*br\s*/?\s*>#is', "\n", $wikitext );
	}

	/**
	 * @param string &$wikitext
	 * @return void
	 */
	protected function removeMagicWords( string &$wikitext ): void {
		// Remove parser functions {{#...}} (any)
		$wikitext = preg_replace( '#{{\s*\#.*?}}#s', '', $wikitext );
		// Remove behavior switches, __...__
		$wikitext = preg_replace( '#__\w+__#', '', $wikitext );
		$variables = $this->magicWordFactory->getVariableIDs();
		foreach ( $variables as $var ) {
			// Remove any `{{$var}}`
			$wikitext = preg_replace( '#{{\s*' . preg_quote( $var, '#' ) . '\s*}}#i', '', $wikitext );
		}

		// Remove {{DISPLAYTITLE...}} specifically
		$wikitext = preg_replace( '#{{\s*displaytitle\s*(:[^}]*)?}}#i', '', $wikitext );
	}

	/**
	 * @param string &$wikitext
	 * @return void
	 */
	protected function removeComments( string &$wikitext ): void {
		// Remove comments <!-- ... -->
		$wikitext = preg_replace( '#<!--(.*?)-->#s', '', $wikitext );
	}

	/**
	 * Conversion:
	 *  {{TemplateName|param1|param2=value2}} or
	 *  {{TemplateName
	 *  |param1
	 *  |param2=value2
	 *  }}  -->
	 *    Template: TemplateName
	 *        * param1
	 *        * param2: value2
	 *
	 *   OR
	 *   {{TemplateName}} --> Template: TemplateName
	 *
	 * @param string &$wikitext
	 * @param bool $atomic
	 * @param int $lvl
	 * @return void
	 */
	protected function convertTemplates( string &$wikitext, bool $atomic = true, int $lvl = 1 ): void {
		$wikitext = preg_replace_callback(
			'/\{\{(?>[^{}]+|(?R))*\}\}/x',
			function ( $template ) use ( $atomic, $lvl ) {
				$template = $template[0];
				// Split name and params
				$innerContent = trim( substr( $template, 2, -2 ) );
				// Escape inner templates
				$this->convertTemplates( $innerContent, false, $lvl + 1 );

				$parts = preg_split( '/\s*\|\s*/', $innerContent );
				$templateName = array_shift( $parts );
				$result = "Template: " . trim( $templateName ) . "\n";
				foreach ( $parts as $param ) {
					$param = trim( $param );
					if ( str_contains( $param, '=' ) ) {
						[ $key, $value ] = explode( '=', $param, 2 );
						$this->convertTemplates( $value, false );
						$result .= str_repeat( "\t", $lvl ) . "* " . trim( $key ) . ": " . trim( $value ) . "\n";
					} else {
						$result .= str_repeat( "\t", $lvl ) . "* " . $param . "\n";
					}
				}
				return $atomic ? $this->makeAtomic( trim( $result ) ) : trim( $result );
			},
			$wikitext
		);
		$wikitext = preg_replace_callback(
			'#{{\s*([^\|\}]+)}}#s',
			function ( $matches ) use ( $atomic ) {
				$templateName = trim( $matches[1] );
				return $atomic ? $this->makeAtomic( "Template: $templateName" ) : "Template: $templateName";
			},
			$wikitext
		);
	}

	/**
	 * @param string &$wikitext
	 * @return void
	 */
	private function convertTags( string &$wikitext ): void {
		$noConvertTags = [ 'pre', 'code', 'nowiki', 'syntaxhighlight' ];

		// Convert <tag>...</tag> to atomic blocks
		$wikitext = preg_replace_callback(
			'#<([\w:-]+)\s*([^>/]*?)(?:\s*/>|>(.*?)</\1>)#is',
			function ( $matches ) use ( $noConvertTags ) {
				$tagName = trim( $matches[1] );
				if ( in_array( strtolower( $tagName ), $noConvertTags, true ) ) {
					// Do not convert these tags
					return $matches[0];
				}
				// Result [tagName attr1=value] Content
				$attributes = trim( $matches[2] );
				$content = isset( $matches[3] ) ? trim( $matches[3] ) : '';
				$attributes = explode( ' ', $attributes );
				$attributes = implode( '; ', $attributes );

				return $this->makeAtomic( "[$tagName " . $attributes . "] " . $content );
			},
			$wikitext
		);

		// Replace nowiki with code
		$wikitext = preg_replace_callback(
			'#<nowiki>(.*?)</nowiki>#is',
			static function ( $matches ) {
				$content = trim( $matches[1] );
				return "<code>" . $content . "</code>";
			},
			$wikitext
		);

		// Replace syntaxhighlight with `<pre>` blocks
		$wikitext = preg_replace_callback(
			'#<syntaxhighlight(?:\s+lang="([^"]*)")?>(.*?)</syntaxhighlight>#is',
			static function ( $matches ) {
				$language = isset( $matches[1] ) ? trim( $matches[1] ) : '';
				$content = trim( $matches[2] );
				$languageAttr = $language !== '' ? " lang=\"$language\"" : '';
				return "<pre$languageAttr>\n" . $content . "\n</pre>";
			},
			$wikitext
		);

		// Wrap all "pre" tags in atomic blocks
		$wikitext = preg_replace_callback(
			'#<pre.*?>.*?</pre>#is',
			function ( $matches ) {
				return $this->makeAtomic( trim( $matches[0] ) );
			},
			$wikitext
		);
	}

	/**
	 * Detect table headers:
	 *  - if set, convert to:
	 *  Row1:
	 *  * Header1: Cell1
	 *  * Header2: Cell2
	 *  Row2:
	 *  * Header1: Cell1
	 *  * Header2: Cell2
	 *  -if not set, convert to:
	 *  Row1:
	 *  * Cell1
	 *  * Cell2
	 *  ...
	 * @param string &$wikitext
	 * @return void
	 */
	private function convertTables( string &$wikitext ): void {
		$wikitext = preg_replace_callback(
	'#\{\|(.+?)\|\}#s',
			function ( $matches ) {
				$tableContent = trim( $matches[1] );
				$lines = preg_split( '/\r\n|\r|\n/', $tableContent );
				$result = '';
				$headers = [];
				$caption = '';
				$rowData = [];
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( str_starts_with( $line, '|+' ) ) {
						// Caption
						$caption = ltrim( $line, '|+' );
						continue;
					}
					if ( str_starts_with( $line, '!' ) ) {
						// Header row
						$cells = explode( '!!', ltrim( $line, '!' ) );
						$headers = array_merge( $headers, array_map( static function ( $h ) {
							$h = trim( $h );
							$bits = explode( '|', $h );
							return end( $bits );
						}, $cells ) );
					} elseif ( str_starts_with( $line, '|-' ) ) {
						// New row, dump row
						if ( empty( $rowData ) ) {
							continue;
						}
						$result .= "\n";
						foreach ( $rowData as $index => $cell ) {
							$cell = trim( $cell );
							if ( isset( $headers[$index] ) && $headers[$index] !== '' ) {
								$result .= $headers[$index] . ": " . $cell . ",";
							} else {
								$result .= $cell . ", ";
							}
						}
						$rowData = [];
					} elseif ( str_starts_with( $line, '|' ) ) {
						// Data row
						$cells = explode( '||', ltrim( $line, '|' ) );
						$cells = array_filter( $cells, fn ( $cell ) => trim( $cell ) !== '' );
						$rowData = array_merge( $rowData, array_map( static function ( $c ) {
							$c = trim( $c );
							$output = '';
							// Detect row spans and col spans
							$rowspan = null;
							$colspan = null;
							if ( preg_match( '/\s*rowspan\s*=\s*"\s*(\d+)\s*"/i', $c, $match ) ) {
								$rowspan = $match[1];
							}
							if ( preg_match( '/\s*colspan\s*=\s*"\s*(\d+)\s*"/i', $c, $match ) ) {
								$colspan = $match[1];
							}
							// Get last bit separated by `|`
							if ( str_ends_with( $c, '|' ) ) {
								return '';
							}
							$parts = explode( '|', $c );
							$content = end( $parts );
							if ( empty( $content ) ) {
								return '';
							}
							if ( $rowspan !== null ) {
								$output .= " [rowspan=$rowspan] ";
							}
							if ( $colspan !== null ) {
								$output .= " [colspan=$colspan] ";
							}
							return trim( $content ) . $output;
						}, $cells ) );
					}
				}
				if ( !empty( $rowData ) ) {
					// Dump last row if set
					$result .= "\n";
					foreach ( $rowData as $index => $cell ) {
						$cell = trim( $cell );
						if ( isset( $headers[$index] ) && $headers[$index] !== '' ) {
							$result .= $headers[$index] . ": " . $cell . ",";
						} else {
							$result .= $cell . ", ";
						}
					}
				}
				return $this->makeAtomic( "Table: $caption\n" . trim( $result ) );
			},
			$wikitext
		);
	}

	/**
	 * Insert "<!-- RAG section break -->" before every section, unless previous section is empty
	 *
	 *  Regex:
	 *  ^(={1,6}\s*.+?\s*={1,6}) -> matches headings from level 1 to 6
	 *  (.*?)(?=^={1,6}\s*.+?\s*={1,6}|$) -> lazily matches all content until next heading or end of text
	 * @param string &$wikitext
	 * @return void
	 */
	private function splitSections( string &$wikitext ): void {
		$prevEmpty = false;
		$wikitext = preg_replace_callback(
			'#^(={1,6}\s*.+?\s*={1,6})\s*(.*?)(?=^={1,6}\s*.+?\s*={1,6}|$)#ms',
			static function ( $matches ) use ( &$prevEmpty ) {
				if ( $prevEmpty ) {
					$prevEmpty = empty( $matches[2] );
					return $matches[0] . "\n";
				}
				$prevEmpty = empty( $matches[2] );
				$sectionHeader = trim( $matches[1] );
				return "\n<!-- RAG section break -->\n" . $sectionHeader . "\n" . $matches[2] . "\n";
			},
			$wikitext
		);
	}

	/**
	 * @param string &$wikitext
	 * @return void
	 */
	private function cleanUp( string &$wikitext ): void {
		// Remove multiple consecutive blank lines
		$wikitext = preg_replace( '#\n{2,}#', "\n", $wikitext );
		// Trim leading and trailing whitespace
		$wikitext = trim( $wikitext );
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function makeAtomic( string $text ): string {
		return "\n<!-- RAG atomic block start -->\n" . $text . "\n<!-- RAG atomic block end -->\n";
	}

	/**
	 * @param string &$wikitext
	 * @return array
	 */
	private function maskTags( string &$wikitext ): array {
		$masked = [];
		$wikitext = preg_replace_callback(
			'#<(\w+)(\s*[^>/]*?)(?:\s*/>|>(.*?)</\1>)#is',
			static function ( $matches ) use ( &$masked ) {
				$fullTag = $matches[0];
				$placeholder = '<TAG_MASK_' . count( $masked ) . '>';
				$masked[$placeholder] = $fullTag;
				return $placeholder;
			},
			$wikitext
		);
		return $masked;
	}

	/**
	 * @param string &$wikitext
	 * @param array $maskedTags
	 * @return void
	 */
	private function unmaskTags( string &$wikitext, array $maskedTags ): void {
		foreach ( $maskedTags as $placeholder => $originalTag ) {
			$wikitext = str_replace( $placeholder, $originalTag, $wikitext );
		}
	}
}
