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

class ReindentAndAlignObjOps extends AdditionalPass {
	const ALIGNABLE_OBJOP = "\x2 OBJOP%d.%d.%d \x3";

	const ALIGN_WITH_INDENT = 1;

	const ALIGN_WITH_SPACES = 2;

	public function candidate($source, $foundTokens) {
		if (
			isset($foundTokens[T_OBJECT_OPERATOR]) ||
			isset($foundTokens[T_DOUBLE_COLON])
		) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';

		$levelCounter = 0;
		$levelEntranceCounter = [];
		$contextCounter = [];
		$touchCounter = [];
		$alignType = [];
		$printedPlaceholder = [];
		$maxContextCounter = [];
		$touchedParenOpen = false;
		$touchedFunction = false;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case ST_QUOTE:
				$this->appendCode($text);
				$this->printUntilTheEndOfString();
				break;
			case T_CLOSE_TAG:
				$this->appendCode($text);
				$this->printUntil(T_OPEN_TAG);
				break;
			case T_START_HEREDOC:
				$this->appendCode($text);
				$this->printUntil(T_END_HEREDOC);
				break;

			case T_WHILE:
			case T_IF:
			case T_FOR:
			case T_FOREACH:
			case T_SWITCH:
				$this->appendCode($text);
				$this->printUntil(ST_PARENTHESES_OPEN);
				$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				break;

			case T_NEW:
				$this->appendCode($text);
				if ($touchedParenOpen) {
					$touchedParenOpen = false;
					$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, ST_COMMA]);
					if (ST_PARENTHESES_OPEN == $foundToken) {
						$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
						$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
						$this->printUntilAny([ST_PARENTHESES_CLOSE, ST_COMMA]);
					} elseif (ST_PARENTHESES_CLOSE == $foundToken) {
						$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
					}
				}
				break;

			case T_FUNCTION:
				$this->appendCode($text);
				$touchedFunction = true;
				break;

			case T_VARIABLE:
			case T_STRING:
				$this->appendCode($text);
				if (!isset($levelEntranceCounter[$levelCounter])) {
					$levelEntranceCounter[$levelCounter] = 0;
				}
				if (!isset($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
					$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]] = 0;
				}
				break;

			case ST_CURLY_OPEN:
			case T_CURLY_OPEN:
			case T_DOLLAR_OPEN_CURLY_BRACES:
				$this->appendCode($text);
				$curlyOpenType = $id;
				if ($touchedFunction) {
					$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
					$touchedFunction = false;
					$curlyOpenType = T_FUNCTION;
				}
				$touchedCurlyBlock[] = $curlyOpenType;
				break;

			case ST_CURLY_CLOSE:
				$this->appendCode($text);
				$curlyOpenType = array_pop($touchedCurlyBlock);
				if (T_FUNCTION == $curlyOpenType) {
					--$levelCounter;
				}
				break;

			case ST_PARENTHESES_OPEN:
				$touchedParenOpen = true;
				$this->appendCode($text);
				if (!$this->hasLnInBlock($this->tkns, $this->ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE)) {
					$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					break;
				}
				$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
				break;

			case ST_BRACKET_OPEN:
				$this->appendCode($text);
				if (!$this->hasLnInBlock($this->tkns, $this->ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE)) {
					$this->printBlock(ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
					break;
				}
				$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
				break;

			case ST_PARENTHESES_CLOSE:
			case ST_BRACKET_CLOSE:
				--$levelCounter;
				$this->appendCode($text);
				break;

			case T_DOUBLE_COLON:
				if (!$this->hasLnBefore()) {
					$this->appendCode($text);
					break;
				}
			case T_OBJECT_OPERATOR:
				if ($levelCounter < 0) {
					$levelCounter = 0;
				}
				if (!isset($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
					$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]] = 0;
				}
				if (0 == $touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]) {
					++$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
					if ($this->hasLnBefore()) {
						$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = self::ALIGN_WITH_INDENT;
						$this->appendCode($this->getIndent(+1) . $text);
						$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, ST_SEMI_COLON, $this->newLine]);
						if (ST_SEMI_COLON == $foundToken) {
							$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
						} elseif (ST_PARENTHESES_OPEN == $foundToken || ST_PARENTHESES_CLOSE == $foundToken) {
							$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
							$this->indentParenthesesContent();
						}
						break;
					}
					$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = self::ALIGN_WITH_SPACES;
					if (!isset($printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]])) {
						$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]] = 0;
					}
					++$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]];
					$placeholder = sprintf(
						self::ALIGNABLE_OBJOP,
						$levelCounter,
						$levelEntranceCounter[$levelCounter],
						$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]
					);
					$this->appendCode($placeholder . $text);
					$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, ST_SEMI_COLON, ST_EQUAL, $this->newLine, ST_COMMA]);
					if (ST_SEMI_COLON == $foundToken || ST_EQUAL == $foundToken || ST_COMMA == $foundToken) {
						$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
					} elseif (ST_PARENTHESES_OPEN == $foundToken) {
						if (!$this->hasLnInBlock($this->tkns, $this->ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE)) {
							$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
							break;
						}
						$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
						$this->injectPlaceholderParenthesesContent($placeholder);
					} elseif (ST_PARENTHESES_CLOSE == $foundToken) {
						$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
						$this->injectPlaceholderParenthesesContent($placeholder);
					}
					break;
				} elseif ($this->hasLnBefore() || $this->hasLnLeftToken()) {
					++$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
					if (self::ALIGN_WITH_SPACES == $alignType[$levelCounter][$levelEntranceCounter[$levelCounter]]) {
						++$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]];
						$placeholder = sprintf(
							self::ALIGNABLE_OBJOP,
							$levelCounter,
							$levelEntranceCounter[$levelCounter],
							$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]
						);
						$this->appendCode($placeholder . $text);
						$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, ST_SEMI_COLON, $this->newLine]);
						if (ST_SEMI_COLON == $foundToken) {
							$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
						} elseif (
							ST_PARENTHESES_OPEN == $foundToken &&
							!$this->hasLnInBlock($this->tkns, $this->ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE)
						) {
							$this->printBlock(ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
							break;
						} elseif (ST_PARENTHESES_OPEN == $foundToken || ST_PARENTHESES_CLOSE == $foundToken) {
							$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
							$this->injectPlaceholderParenthesesContent($placeholder);
						}
						break;
					}
					$this->appendCode($this->getIndent(+1) . $text);
					$foundToken = $this->printUntilAny([ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE, ST_SEMI_COLON, $this->newLine]);
					if (ST_SEMI_COLON == $foundToken) {
						$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
					} elseif (ST_PARENTHESES_OPEN == $foundToken || ST_PARENTHESES_CLOSE == $foundToken) {
						$this->incrementCounters($levelCounter, $levelEntranceCounter, $contextCounter, $maxContextCounter, $touchCounter, $alignType, $printedPlaceholder);
						$this->indentParenthesesContent();
					}
					break;
				}
				$this->appendCode($text);
				break;

			case T_COMMENT:
			case T_DOC_COMMENT:
				if (
					isset($alignType[$levelCounter]) &&
					isset($levelEntranceCounter[$levelCounter]) &&
					isset($alignType[$levelCounter][$levelEntranceCounter[$levelCounter]]) &&
					($this->hasLnBefore() || $this->hasLnLeftToken()) &&
					!$this->leftUsefulTokenIs(ST_CURLY_CLOSE)
				) {
					if (self::ALIGN_WITH_SPACES == $alignType[$levelCounter][$levelEntranceCounter[$levelCounter]]) {
						++$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]];
						$this->appendCode(
							sprintf(
								self::ALIGNABLE_OBJOP,
								$levelCounter,
								$levelEntranceCounter[$levelCounter],
								$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]
							)
						);
					} elseif (self::ALIGN_WITH_INDENT == $alignType[$levelCounter][$levelEntranceCounter[$levelCounter]]) {
						$this->appendCode($this->getIndent(+1));
					}
				}
				$this->appendCode($text);
				if ($this->leftUsefulTokenIs([T_OBJECT_OPERATOR, T_DOUBLE_COLON]) && $this->hasLn($text)) {
					$this->appendCode($this->getIndent(+1));
				}
				break;

			case ST_COMMA:
			case ST_SEMI_COLON:
			case ST_EQUAL:
				if (!isset($levelEntranceCounter[$levelCounter])) {
					$levelEntranceCounter[$levelCounter] = 0;
				}
				++$levelEntranceCounter[$levelCounter];
				$this->appendCode($text);
				break;

			case T_WHITESPACE:
				$this->appendCode($text);
				if ($this->leftUsefulTokenIs([T_OBJECT_OPERATOR, T_DOUBLE_COLON]) && $this->hasLn($text)) {
					$this->appendCode($this->getIndent(+1));
				}
				break;

			default:
				$touchedParenOpen = false;
				$this->appendCode($text);
				break;
			}
		}

		foreach ($maxContextCounter as $level => $entrances) {
			foreach ($entrances as $entrance => $context) {
				for ($j = 0; $j <= $context; ++$j) {
					if (!isset($printedPlaceholder[$level][$entrance][$j])) {
						continue;
					}
					if (0 === $printedPlaceholder[$level][$entrance][$j]) {
						continue;
					}

					$placeholder = sprintf(self::ALIGNABLE_OBJOP, $level, $entrance, $j);
					if (1 === $printedPlaceholder[$level][$entrance][$j]) {
						$this->code = str_replace($placeholder, '', $this->code);
						continue;
					}

					$lines = explode($this->newLine, $this->code);
					$linesWithObjop = [];

					foreach ($lines as $idx => $line) {
						if (false !== strpos($line, $placeholder)) {
							$linesWithObjop[] = $idx;
						}
					}

					$farthest = 0;
					foreach ($linesWithObjop as $idx) {
						$farthest = max($farthest, strpos($lines[$idx], $placeholder . '->'));
					}
					foreach ($linesWithObjop as $idx) {
						$line = $lines[$idx];
						$current = strpos($line, $placeholder);
						$delta = abs($farthest - $current);
						if ($delta > 0) {
							$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
							$lines[$idx] = $line;
						}
					}

					$this->code = str_replace($placeholder, '', implode($this->newLine, $lines));
				}
			}
		}
		$this->code = preg_replace('/' . str_replace('%d', '.*', preg_quote(self::ALIGNABLE_OBJOP)) . '/', '', $this->code);

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Align object operators.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// From:
$aaaaa->b
->c;

// To:
$aaaaa->b
      ->c;
?>
EOT;
	}

	protected function incrementCounters(
		&$levelCounter,
		&$levelEntranceCounter,
		&$contextCounter,
		&$maxContextCounter,
		&$touchCounter,
		&$alignType,
		&$printedPlaceholder
	) {
		++$levelCounter;
		if (!isset($levelEntranceCounter[$levelCounter])) {
			$levelEntranceCounter[$levelCounter] = 0;
		}
		++$levelEntranceCounter[$levelCounter];
		if (!isset($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
			$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
			$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
			$touchCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
			$alignType[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
			$printedPlaceholder[$levelCounter][$levelEntranceCounter[$levelCounter]][$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]] = 0;
		}
		++$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
		$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = max($maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]], $contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]);
	}

	protected function indentParenthesesContent() {
		$count = 0;
		$sizeofTokens = sizeof($this->tkns);
		for ($i = $this->ptr; $i < $sizeofTokens; ++$i) {
			$token = &$this->tkns[$i];
			list($id, $text) = $this->getToken($token);
			if (
				(T_WHITESPACE == $id || T_DOC_COMMENT == $id || T_COMMENT == $id)
				&& $this->hasLn($text)
			) {
				$token[1] = $text . $this->getIndent(+1);
				continue;
			}
			if (ST_PARENTHESES_OPEN == $id) {
				++$count;
			}
			if (ST_PARENTHESES_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function injectPlaceholderParenthesesContent($placeholder) {
		$count = 0;
		$sizeofTokens = sizeof($this->tkns);
		for ($i = $this->ptr; $i < $sizeofTokens; ++$i) {
			$token = &$this->tkns[$i];
			list($id, $text) = $this->getToken($token);
			if ((T_WHITESPACE == $id || T_DOC_COMMENT == $id || T_COMMENT == $id)
				&& $this->hasLn($text)) {
				$token[1] = str_replace($this->newLine, $this->newLine . $placeholder, $text);
				continue;
			}
			if (ST_PARENTHESES_OPEN == $id) {
				++$count;
			}
			if (ST_PARENTHESES_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	private function hasLnInBlock($tkns, $ptr, $start, $end) {
		$sizeOfTkns = sizeof($tkns);
		$count = 0;
		for ($i = $ptr; $i < $sizeOfTkns; ++$i) {
			$token = $tkns[$i];
			list($id, $text) = $this->getToken($token);
			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
			if ($this->hasLn($text)) {
				return true;
			}
		}
		return false;
	}
}
