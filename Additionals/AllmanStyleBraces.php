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

final class AllmanStyleBraces extends AdditionalPass {
	const OTHER_BLOCK = '';

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[ST_CURLY_OPEN])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$blockStack = [];
		$foundStack = [];
		$currentIndentation = 0;
		$touchedCaseOrDefault = false;
		$touchedSwitch = false;

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_CASE:
			case T_DEFAULT:
				$touchedCaseOrDefault = true;
				$this->appendCode($text);
				break;

			case T_BREAK:
				$touchedCaseOrDefault = false;
				$this->appendCode($text);
				break;

			case T_CLASS:
			case T_FUNCTION:
				$currentIndentation = 0;
				$poppedID = end($foundStack);
				if (true === $poppedID['implicit']) {
					list($prevId, $prevText) = $this->inspectToken(-1);
					$currentIndentation = substr_count($prevText, $this->indentChar, strrpos($prevText, "\n"));
				}
				$this->appendCode($text);
				break;

			case ST_CURLY_OPEN:
				$block = self::OTHER_BLOCK;
				if ($touchedSwitch) {
					$touchedSwitch = false;
					$block = T_SWITCH;
				}
				$blockStack[] = $block;

				if ($this->leftUsefulTokenIs([ST_PARENTHESES_CLOSE, T_ELSE, T_FINALLY, T_DO, T_STRING])) {
					if (!$this->hasLnLeftToken()) {
						$this->appendCode($this->getCrlfIndent());
					}
				}
				$indentToken = [
					'id' => $id,
					'implicit' => true,
				];
				$adjustedIndendation = max($currentIndentation - $this->indent, 0);
				if ($touchedCaseOrDefault) {
					++$adjustedIndendation;
				}
				$this->appendCode(str_repeat($this->indentChar, $adjustedIndendation) . $text);
				$currentIndentation = 0;
				if ($this->hasLnAfter()) {
					$indentToken['implicit'] = false;
					$this->setIndent(+1);
				}
				if (
					!$this->hasLnAfter() &&
					!$this->leftUsefulTokenIs([T_OBJECT_OPERATOR, T_DOUBLE_COLON]) &&
					!$this->rightTokenIs([T_COMMENT, T_DOC_COMMENT])
				) {
					$this->setIndent(+1);
					$this->appendCode($this->getCrlfIndent());
					$this->setIndent(-1);
				}
				$foundStack[] = $indentToken;
				break;

			case T_DOLLAR_OPEN_CURLY_BRACES:
			case T_CURLY_OPEN:
				$this->appendCode($text);
				$this->printCurlyBlock();
				break;

			case ST_BRACKET_OPEN:
			case ST_PARENTHESES_OPEN:
				$blockStack[] = self::OTHER_BLOCK;
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

			case ST_COMMA:
				$this->appendCode($text);
				$foundIndent = &$foundStack[sizeof($foundStack) - 1];
				if ($this->hasLnAfter() && $foundIndent['implicit']) {
					$foundIndent['implicit'] = false;
					$this->setIndent(+1);
				}
				break;

			case ST_BRACKET_CLOSE:
			case ST_PARENTHESES_CLOSE:
			case ST_CURLY_CLOSE:
				$poppedID = array_pop($foundStack);
				$poppedBlock = array_pop($blockStack);
				if (T_SWITCH == $poppedBlock) {
					$touchedCaseOrDefault = false;
					$this->setIndent(-1);
				} elseif (false === $poppedID['implicit']) {
					$this->setIndent(-1);
				}
				$this->appendCode($text);
				break;

			case T_ELSE:
			case T_ELSEIF:
			case T_FINALLY:
				list($prevId, $prevText) = $this->getToken($this->leftToken());
				if (!$this->hasLn($prevText) && T_OPEN_TAG != $prevId) {
					$this->appendCode($this->getCrlfIndent());
					if ($touchedCaseOrDefault) {
						$this->appendCode($this->indentChar);
					}
				}
				$this->appendCode($text);
				break;

			case T_CATCH:
				if (' ' == substr($this->code, -1, 1)) {
					$this->code = substr($this->code, 0, -1);
				}
				if (!$this->hasLnLeftToken()) {
					$this->appendCode($this->getCrlfIndent());
					if ($touchedCaseOrDefault) {
						$this->appendCode($this->indentChar);
					}
				}
				$this->appendCode($text);
				break;

			case T_SWITCH:
				$touchedSwitch = true;
				$this->appendCode($text);
				break;

			default:
				$this->appendCode($text);
				break;
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Transform all curly braces into Allman-style.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
if ($a) {

}


if ($a)
{

}
?>
EOT;
	}
}
