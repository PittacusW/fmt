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

class AutoPreincrement extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_INC]) || isset($foundTokens[T_DEC])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		for ($this->ptr = sizeof($this->tkns) - 1; $this->ptr >= 0; --$this->ptr) {
			$token = $this->tkns[$this->ptr];
			$tokenRef = &$this->tkns[$this->ptr];

			$id = $token[0];
			if (!(T_INC == $id || T_DEC == $id)) {
				continue;
			}

			if (
				!$this->leftUsefulTokenIs([
					ST_BRACKET_CLOSE,
					ST_CURLY_CLOSE,
					T_STRING,
					T_VARIABLE,
				])
				||
				!$this->rightUsefulTokenIs([
					ST_SEMI_COLON,
					ST_PARENTHESES_CLOSE,
				])
			) {
				continue;
			}

			$this->findVariableLeftEdge();

			if (
				$this->leftUsefulTokenIs([
					ST_SEMI_COLON,
					ST_CURLY_OPEN,
					ST_CURLY_CLOSE,
					T_OPEN_TAG,
				])
			) {
				$this->refInsert($this->tkns, $this->ptr, $token);
				$tokenRef = null;
			}
		}

		return $this->render();
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Automatically convert postincrement to preincrement.';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a++;
$b--;
func($a++);

++$a;
--$b;
func($a++);
?>
EOT;
	}

	private function findVariableLeftEdge() {
		$this->skipBlocks();

		$leftIdx = $this->leftUsefulTokenIdx();
		$idLeftToken = $this->tkns[$leftIdx][0];

		if (ST_DOLLAR == $idLeftToken) {
			$this->ptr = $leftIdx;
			$leftIdx = $this->leftUsefulTokenIdx();
			$idLeftToken = $this->tkns[$leftIdx][0];
		}

		if (T_OBJECT_OPERATOR == $idLeftToken) {
			$this->findVariableLeftEdge();
			return;
		}

		if (T_DOUBLE_COLON == $idLeftToken) {
			if (!$this->leftUsefulTokenIs([T_STRING])) {
				$this->findVariableLeftEdge();
				return;
			}

			$this->refWalkBackUsefulUntil($this->tkns, $this->ptr, [T_NS_SEPARATOR, T_STRING]);
			$this->ptr = $this->rightUsefulTokenIdx();
		}

		return;
	}

	private function skipBlocks() {
		do {
			$this->ptr = $this->leftUsefulTokenIdx();
			$id = $this->tkns[$this->ptr][0];

			if (ST_BRACKET_CLOSE == $id) {
				$this->refWalkBlockReverse($this->tkns, $this->ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
			} elseif (ST_CURLY_CLOSE == $id) {
				$this->refWalkCurlyBlockReverse($this->tkns, $this->ptr);
			} elseif (ST_PARENTHESES_CLOSE == $id) {
				$this->refWalkBlockReverse($this->tkns, $this->ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
			}

			$id = $this->tkns[$this->ptr][0];
		} while (!(ST_DOLLAR == $id || T_VARIABLE == $id));
	}
}