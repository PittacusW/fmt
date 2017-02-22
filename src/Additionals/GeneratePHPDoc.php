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

final class GeneratePHPDoc extends AdditionalPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_FUNCTION]) || isset($foundTokens[T_PUBLIC]) || isset($foundTokens[T_PROTECTED]) || isset($foundTokens[T_PRIVATE]) || isset($foundTokens[T_STATIC]) || isset($foundTokens[T_VAR])) {
			return true;
		}

		return false;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$touchedVisibility = false;
		$touchedDocComment = false;
		$visibilityIdx = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case T_DOC_COMMENT:
				$touchedDocComment = true;
				break;

			case T_CLASS:
				if ($touchedDocComment) {
					$touchedDocComment = false;
				}
				break;

			case T_FINAL:
			case T_ABSTRACT:
			case T_PUBLIC:
			case T_PROTECTED:
			case T_PRIVATE:
			case T_STATIC:
			case T_VAR:
				if (!$this->leftTokenIs([T_FINAL, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_VAR])) {
					$touchedVisibility = true;
					$visibilityIdx = $this->ptr;
				}

				break;
			case T_VARIABLE:
				if (!$this->leftTokenIs([T_FINAL, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_VAR])) {
					break;
				}
				if ($touchedDocComment) {
					$touchedDocComment = false;
					break;
				}
				if (!$touchedVisibility) {
					break;
				}
				$origIdx = $visibilityIdx;

				$type = 'mixed';
				if ($this->rightTokenIs([ST_EQUAL])) {
					$this->walkUntil(ST_EQUAL);
					if ($this->rightTokenIs([T_ARRAY, ST_BRACKET_OPEN])) {
						$type = 'array';
					} elseif ($this->rightTokenIs([T_LNUMBER])) {
						$type = 'int';
					} elseif ($this->rightTokenIs([T_DNUMBER])) {
						$type = 'float';
					} elseif ($this->rightTokenIs([T_CONSTANT_ENCAPSED_STRING])) {
						$type = 'string';
					}
				}

				$propToken = &$this->tkns[$origIdx];
				$propToken[1] = $this->renderPropertyDocBlock($type) . $propToken[1];
				$touchedVisibility = false;

				break;
			case T_FUNCTION:
				if ($touchedDocComment) {
					$touchedDocComment = false;
					break;
				}
				$origIdx = $visibilityIdx;
				if (!$touchedVisibility) {
					$origIdx = $this->ptr;
				}
				list($ntId) = $this->getToken($this->rightToken());
				if (T_STRING != $ntId) {
					$this->appendCode($text);
					break;
				}
				$this->walkUntil(ST_PARENTHESES_OPEN);
				$paramStack = [];
				$tmp = ['type' => '', 'name' => ''];
				$count = 1;
				while (list($index, $token) = each($this->tkns)) {
					list($id, $text) = $this->getToken($token);
					$this->ptr = $index;

					if (ST_PARENTHESES_OPEN == $id) {
						++$count;
					}
					if (ST_PARENTHESES_CLOSE == $id) {
						--$count;
					}
					if (0 == $count) {
						break;
					}
					if (T_STRING == $id || T_NS_SEPARATOR == $id) {
						$tmp['type'] .= $text;
						continue;
					}
					if (T_VARIABLE == $id) {
						if ($this->leftTokenIs([T_ARRAY]) || $this->rightTokenIs([ST_EQUAL]) && $this->walkUntil(ST_EQUAL) && $this->rightTokenIs([T_ARRAY, ST_BRACKET_OPEN])) {
							$tmp['type'] = 'array';
						}
						$tmp['name'] = $text;
						$paramStack[] = $tmp;
						$tmp = ['type' => '', 'name' => ''];
						continue;
					}
				}

				$returnStack = '';
				if (!$this->rightUsefulTokenIs(ST_SEMI_COLON)) {
					$this->walkUntil(ST_CURLY_OPEN);
					$count = 1;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->getToken($token);
						$this->ptr = $index;

						if (ST_CURLY_OPEN == $id) {
							++$count;
						}
						if (ST_CURLY_CLOSE == $id) {
							--$count;
						}
						if (0 == $count) {
							break;
						}
						if (T_RETURN == $id) {
							if ($this->rightTokenIs([T_DNUMBER])) {
								$returnStack = 'float';
							} elseif ($this->rightTokenIs([T_LNUMBER])) {
								$returnStack = 'int';
							} elseif ($this->rightTokenIs([T_VARIABLE])) {
								$returnStack = 'mixed';
							} elseif ($this->rightTokenIs([ST_SEMI_COLON])) {
								$returnStack = 'null';
							}
						}
					}
				}

				$funcToken = &$this->tkns[$origIdx];
				$funcToken[1] = $this->renderFunctionDocBlock($paramStack, $returnStack) . $funcToken[1];
				$touchedVisibility = false;
			}
		}

		return implode('', array_map(function ($token) {
			list(, $text) = $this->getToken($token);
			return $text;
		}, $this->tkns));
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Automatically generates PHPDoc blocks';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
class A {
	function a(Someclass $a) {
		return 1;
	}
}
?>
to
<?php
class A {
	/**
	 * @param Someclass $a
	 * @return int
	 */
	function a(Someclass $a) {
		return 1;
	}
}
?>
EOT;
	}

	private function renderFunctionDocBlock(array $paramStack, $returnStack) {
		if (empty($paramStack) && empty($returnStack)) {
			return '';
		}
		$str = ' /**' . $this->newLine;
		foreach ($paramStack as $param) {
			$str .= rtrim(' * @param ' . $param['type']) . ' ' . $param['name'] . $this->newLine;
		}
		if (!empty($returnStack)) {
			$str .= ' * @return ' . $returnStack . $this->newLine;
		}
		$str .= ' */' . $this->newLine;
		return $str;
	}

	private function renderPropertyDocBlock($type) {
		return sprintf(' /**%s* @var %s%s */%s',
			$this->newLine,
			$type,
			$this->newLine,
			$this->newLine
		);
	}
}
