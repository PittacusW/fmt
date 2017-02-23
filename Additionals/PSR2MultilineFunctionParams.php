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

final class PSR2MultilineFunctionParams extends AdditionalPass {
	const LINE_BREAK = "\x2 LN \x3";

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_FUNCTION])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_FUNCTION:
				$this->appendCode($text);
				$this->printUntil(ST_PARENTHESES_OPEN);
				$this->appendCode(self::LINE_BREAK);
				$touchedComma = false;
				while (list($index, $token) = each($this->tkns)) {
					list($id, $text) = $this->getToken($token);
					$this->ptr = $index;

					if (ST_PARENTHESES_OPEN === $id) {
						$this->appendCode($text);
						$this->printUntil(ST_PARENTHESES_CLOSE);
						continue;
					} elseif (ST_BRACKET_OPEN === $id) {
						$this->appendCode($text);
						$this->printUntil(ST_BRACKET_CLOSE);
						continue;
					} elseif (ST_PARENTHESES_CLOSE === $id) {
						$this->appendCode(self::LINE_BREAK);
						$this->appendCode($text);
						break;
					}
					$this->appendCode($text);

					if (ST_COMMA === $id && !$this->hasLnAfter()) {
						$touchedComma = true;
						$this->appendCode(self::LINE_BREAK);
					}
				}
				$placeholderReplace = $this->newLine;
				if (!$touchedComma) {
					$placeholderReplace = '';
				}
				$this->code = str_replace(self::LINE_BREAK, $placeholderReplace, $this->code);
				break;
			default:
				$this->appendCode($text);
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Break function parameters into multiple lines.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
// PSR2 Mode - From
function a($a, $b, $c)
{}

// To
function a(
	$a,
	$b,
	$c
) {}
?>
EOT;
	}
}
