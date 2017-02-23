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

final class Reindent extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (
			isset($foundTokens[ST_CURLY_OPEN]) ||
			isset($foundTokens[ST_PARENTHESES_OPEN]) ||
			isset($foundTokens[ST_BRACKET_OPEN])
		) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$this->useCache = true;

		// It scans for indentable blocks, and only indent those blocks
		// which next token possesses a linebreak.
		$foundStack = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];

			if (
				(
					T_WHITESPACE === $id ||
					(T_COMMENT === $id && '//' == substr($text, 0, 2))
				) && $this->hasLn($text)
			) {
				$bottomFoundStack = end($foundStack);
				if (isset($bottomFoundStack['implicit']) && $bottomFoundStack['implicit']) {
					$idx = sizeof($foundStack) - 1;
					$foundStack[$idx]['implicit'] = false;
					$this->setIndent(+1);
				}
			}
			switch ($id) {
			case ST_QUOTE:
				$this->appendCode($text);
				$this->printUntilTheEndOfString();
				break;

			case T_CLOSE_TAG:
				$this->appendCode($text);
				$this->printUntilAny([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO]);
				break;

			case T_START_HEREDOC:
				$this->appendCode($text);
				$this->printUntil(T_END_HEREDOC);
				break;

			case T_CONSTANT_ENCAPSED_STRING:
			case T_ENCAPSED_AND_WHITESPACE:
			case T_STRING_VARNAME:
			case T_NUM_STRING:
				$this->appendCode($text);
				break;

			case T_DOLLAR_OPEN_CURLY_BRACES:
			case T_CURLY_OPEN:
			case ST_CURLY_OPEN:
			case ST_PARENTHESES_OPEN:
			case ST_BRACKET_OPEN:
				$indentToken = [
					'id' => $id,
					'implicit' => true,
				];
				$this->appendCode($text);
				if ($this->hasLnAfter()) {
					$indentToken['implicit'] = false;
					$this->setIndent(+1);
				}
				$foundStack[] = $indentToken;
				break;

			case ST_CURLY_CLOSE:
			case ST_PARENTHESES_CLOSE:
			case ST_BRACKET_CLOSE:
				$poppedID = array_pop($foundStack);
				if (false === $poppedID['implicit']) {
					$this->setIndent(-1);
				}
				$this->appendCode($text);
				break;

			case T_DOC_COMMENT:
				$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
				$this->appendCode($text);
				break;

			case T_COMMENT:
			case T_WHITESPACE:
				if (
					$this->hasLn($text) &&
					$this->rightTokenIs([T_COMMENT, T_DOC_COMMENT]) &&
					$this->rightUsefulTokenIs([T_CASE, T_DEFAULT])
				) {
					$this->setIndent(-1);
					$this->appendCode(str_replace($this->newLine, $this->newLine . $this->getIndent(), $text));
					$this->setIndent(+1);
					break;
				}

			default:
				$hasLn = $this->hasLn($text);
				if ($hasLn) {
					$isNextCurlyParenBracketClose = $this->rightTokenIs([T_CASE, T_DEFAULT, ST_CURLY_CLOSE, ST_PARENTHESES_CLOSE, ST_BRACKET_CLOSE]);
					if (!$isNextCurlyParenBracketClose) {
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
					} elseif ($isNextCurlyParenBracketClose) {
						$this->setIndent(-1);
						$text = str_replace($this->newLine, $this->newLine . $this->getIndent(), $text);
						$this->setIndent(+1);
					}
				}
				$this->appendCode($text);
				break;
			}
		}
		return $this->code;
	}
}
