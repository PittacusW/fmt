<?php
# Copyright (c) 2015, phpfmt and its authors
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
#
# 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

class OrderAndRemoveUseClauses extends AdditionalPass {
	const BLANK_LINE_AFTER_USE_BLOCK = true;

	const OPENER_PLACEHOLDER = "<?php /*\x2 ORDERBY \x3*/";

	const REMOVE_UNUSED = true;

	const SPLIT_COMMA = true;

	const STRIP_BLANK_LINES = true;

	const TRAIT_BLOCK_OPEN = 'TRAIT_BLOCK_OPEN';

	private $sortFunction = null;

	public function __construct(callable $sortFunction = null) {
		$this->sortFunction = $sortFunction;
		if (null == $sortFunction) {
			$this->sortFunction = function ($useStack) {
				natcasesort($useStack);
				return $useStack;
			};
		}
	}

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_USE])) {
			return true;
		}

		return false;
	}

	public function format($source = '') {
		$source = $this->sortWithinNamespaces($source);

		return $source;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Order use block and remove unused imports.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
// From
use C;
use B;

class D {
	function f() {
		new B();
	}
}


// To
use B;

class D {
	function f() {
		new B();
	}
}
EOT;
	}

	protected function sortUseClauses($source, $splitComma, $removeUnused, $stripBlankLines, $blanklineAfterUseBlock) {
		$tokens = token_get_all($source);

		// It scans for T_USE blocks (thus skiping "function () use ()")
		// either in their pure form or aggregated with commas, then it
		// breaks the blocks, purges unused classes and adds missing
		// ones.
		$newTokens = [];
		$useStack = [0 => []];
		$foundComma = false;
		$groupCount = 0;
		$touchedDoubleColon = false;
		$stopTokens = [ST_SEMI_COLON, ST_CURLY_OPEN];
		if ($splitComma) {
			$stopTokens[] = ST_COMMA;
		}
		$aliasList = [];
		$aliasCount = [];
		$unusedImport = [];

		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);

			if (T_DOUBLE_COLON == $id) {
				$newTokens[] = $token;
				$touchedDoubleColon = true;
				continue;
			}

			if (
				(T_TRAIT === $id || T_CLASS === $id) &&
				!$touchedDoubleColon
			) {
				$newTokens[] = $token;
				while (list(, $token) = each($tokens)) {
					list($id, $text) = $this->getToken($token);
					$newTokens[] = $token;
				}
				break;
			}

			$touchedDoubleColon = false;

			if (
				!$stripBlankLines &&
				(
					T_WHITESPACE === $id
					||
					(T_COMMENT === $id && '/' == $text[2])
				) && substr_count($text, $this->newLine) >= 2
			) {
				++$groupCount;
				$useStack[$groupCount] = [];
				$newTokens[] = $token;
				continue;
			}

			if (T_USE === $id && $this->rightTokenSubsetIsAtIdx($tokens, $index, [ST_PARENTHESES_OPEN], $this->ignoreFutileTokens)) {
				$newTokens[] = $token;
				continue;
			}

			if (T_USE === $id || $foundComma) {
				list($useTokens, $foundToken) = $this->walkAndAccumulateStopAtAny($tokens, $stopTokens);

				if (ST_SEMI_COLON == $foundToken) {
					$useStack[$groupCount][] = 'use ' . ltrim($useTokens) . ';';
					$newTokens[] = new SurrogateToken();
					next($tokens);

					$foundComma = false;
				} elseif (ST_COMMA == $foundToken) {
					$useStack[$groupCount][] = 'use ' . ltrim($useTokens) . ';';
					$newTokens[] = new SurrogateToken();
					$newTokens[] = [T_WHITESPACE, $this->newLine . $this->newLine];

					$foundComma = true;
				} elseif (ST_CURLY_OPEN == $foundToken) {
					next($tokens);
					$base = $this->newLine . 'use ' . ltrim($useTokens);

					do {
						list($groupText, $groupFoundToken) = $this->walkAndAccumulateStopAtAny($tokens, [ST_COMMA, ST_CURLY_CLOSE]);
						$useStack[$groupCount][] = $base . trim($groupText) . ';';
						$newTokens[] = new SurrogateToken();
						next($tokens);
					} while (ST_COMMA == $groupFoundToken);
					$foundComma = false;
					$this->walkAndAccumulateUntil($tokens, ST_SEMI_COLON);
				}
				continue;
			}

			$newTokens[] = $token;
		}

		if (empty($useStack[0])) {
			return $source;
		}
		foreach ($useStack as $group => $useClauses) {
			$useStack[$group] = call_user_func($this->sortFunction, $useClauses);
		}
		$useStack = call_user_func_array('array_merge', $useStack);

		foreach ($useStack as $use) {
			$alias = $this->calculateAlias($use);
			$alias = str_replace(ST_SEMI_COLON, '', strtolower($alias));
			$aliasList[$alias] = trim(strtolower($use));
			$aliasCount[$alias] = 0;
		}

		$return = '';
		foreach ($newTokens as $idx => $token) {
			if ($token instanceof SurrogateToken) {
				$return .= array_shift($useStack);
				if ($blanklineAfterUseBlock && !isset($useStack[0])) {
					$return .= $this->newLine;
				}
				continue;
			} elseif (T_WHITESPACE == $token[0] && isset($newTokens[$idx - 1], $newTokens[$idx + 1]) && $newTokens[$idx - 1] instanceof SurrogateToken && $newTokens[$idx + 1] instanceof SurrogateToken) {
				if ($stripBlankLines) {
					$return .= $this->newLine;
					continue;
				}

				$return .= $token[1];
				continue;
			}
			list($id, $text) = $this->getToken($token);
			$lowerText = strtolower($text);
			if (T_STRING === $id && isset($aliasList[$lowerText])) {
				++$aliasCount[$lowerText];
			} elseif (T_DOC_COMMENT === $id) {
				foreach ($aliasList as $alias => $use) {
					if (false !== stripos($text, $alias)) {
						++$aliasCount[$alias];
					}
				}
			}
			$return .= $text;
		}

		if ($removeUnused) {
			$unusedImport = array_keys(
				array_filter(
					$aliasCount, function ($v) {
						return 0 === $v;
					}
				)
			);
		}

		foreach ($unusedImport as $v) {
			$return = str_ireplace($aliasList[$v] . $this->newLine, null, $return);
		}

		return $return;
	}

	private function calculateAlias($use) {
		if (false !== stripos($use, ' as ')) {
			return substr(stristr($use, ' as '), strlen(' as '), -1);
		}
		return basename(str_replace('\\', '/', trim(substr($use, strlen('use'), -1))));
	}

	private function sortWithinNamespaces($source) {
		$classRelatedCount = 0;
		$namespaceCount = 0;
		$tokens = token_get_all($source);
		$touchedTUse = false;
		while (list(, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			if (T_USE === $id) {
				$touchedTUse = true;
			}
			if (
				T_CLASS == $id ||
				T_INTERFACE == $id
			) {
				++$classRelatedCount;
			}
			if (T_NAMESPACE == $id && !$this->rightUsefulTokenIs(T_NS_SEPARATOR)) {
				++$namespaceCount;
			}
		}

		if ($namespaceCount <= 1 && $touchedTUse) {
			return $this->sortUseClauses($source, self::SPLIT_COMMA, self::REMOVE_UNUSED, self::STRIP_BLANK_LINES, self::BLANK_LINE_AFTER_USE_BLOCK && $classRelatedCount > 0);
		}

		$return = '';
		reset($tokens);
		while (list($index, $token) = each($tokens)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_NAMESPACE:
				$return .= $text;
				while (list($index, $token) = each($tokens)) {
					list($id, $text) = $this->getToken($token);
					$this->ptr = $index;
					$return .= $text;
					if (ST_CURLY_OPEN == $id || ST_SEMI_COLON == $id) {
						break;
					}
				}
				$namespaceBlock = '';
				if (ST_CURLY_OPEN === $id) {
					$curlyCount = 1;
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;
						$namespaceBlock .= $text;

						if (ST_CURLY_OPEN == $id) {
							++$curlyCount;
						} elseif (ST_CURLY_CLOSE == $id) {
							--$curlyCount;
						}

						if (0 == $curlyCount) {
							break;
						}
					}
				} elseif (ST_SEMI_COLON === $id) {
					while (list($index, $token) = each($tokens)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;

						if (T_NAMESPACE == $id && !$this->rightUsefulTokenIs(T_NS_SEPARATOR)) {
							prev($tokens);
							break;
						}

						$namespaceBlock .= $text;
					}
				}

				$return .= str_replace(
					self::OPENER_PLACEHOLDER,
					'',
					$this->sortUseClauses(self::OPENER_PLACEHOLDER . $namespaceBlock, self::SPLIT_COMMA, self::REMOVE_UNUSED, self::STRIP_BLANK_LINES, self::BLANK_LINE_AFTER_USE_BLOCK)
				);

				break;
			default:
				$return .= $text;
			}
		}

		return $return;
	}
}
