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

final class PrettyPrintDocBlocks extends AdditionalPass {
	const EMPTY_LINE = "\x2 EMPTYLINE \x3";

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_DOC_COMMENT])) {
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
			if (T_DOC_COMMENT == $id) {
				$text = $this->prettify($text);
			}
			$this->appendCode($text);
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Prettify Doc Blocks';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
/**
 * some description.
 * @param array $b
 * @param LongTypeName $c
 */
function A(array $b, LongTypeName $c) {
}
?>

to
<?php
/**
 * some description.
 * @param array        $b
 * @param LongTypeName $c
 */
function A(array $b, LongTypeName $c) {
}
?>
EOT;
	}

	private function isUTF8($usStr) {
		return (utf8_encode(utf8_decode($usStr)) == $usStr);
	}

	private function prettify($docBlock) {
		$isUTF8 = $this->isUTF8($docBlock);

		if ($isUTF8) {
			$docBlock = utf8_decode($docBlock);
		}

		$groups = [
			'@deprecated' => 1,
			'@link' => 1,
			'@see' => 1,
			'@since' => 1,

			'@author' => 2,
			'@copyright' => 2,
			'@license' => 2,

			'@package' => 3,
			'@subpackage' => 3,

			'@param' => 4,
			'@throws' => 4,
			'@return' => 4,
		];
		$weights = [
			'@package' => 1,
			'@subpackage' => 2,
			'@author' => 3,
			'@copyright' => 4,
			'@license' => 5,
			'@deprecated' => 6,
			'@link' => 7,
			'@see' => 8,
			'@since' => 9,
			'@param' => 10,
			'@throws' => 11,
			'@return' => 12,
		];
		$weightsLen = [
			'@package' => strlen('@package'),
			'@subpackage' => strlen('@subpackage'),
			'@author' => strlen('@author'),
			'@copyright' => strlen('@copyright'),
			'@license' => strlen('@license'),
			'@deprecated' => strlen('@deprecated'),
			'@link' => strlen('@link'),
			'@see' => strlen('@see'),
			'@since' => strlen('@since'),
			'@param' => strlen('@param'),
			'@throws' => strlen('@throws'),
			'@return' => strlen('@return'),
		];

		// Strip envelope
		$docBlock = trim(str_replace(['/**', '*/'], '', $docBlock));
		$lines = explode($this->newLine, $docBlock);
		foreach ($lines as $idx => $v) {
			$v = ltrim($v);
			if ('* ' === substr($v, 0, 2)) {
				$v = substr($v, 2);
			}
			if ('*' === substr($v, 0, 1)) {
				$v = substr($v, 1);
			}
			$lines[$idx] = $v . ':' . $idx;
		}

		// Sort lines
		usort($lines, function ($a, $b) use ($weights, $weightsLen) {
			$weightA = 0;
			foreach ($weights as $pattern => $weight) {
				if (strtolower(substr(ltrim($a), 0, $weightsLen[$pattern])) == $pattern) {
					$weightA = $weight;
					break;
				}
			}

			$weightB = 0;
			foreach ($weights as $pattern => $weight) {
				if (strtolower(substr(ltrim($b), 0, $weightsLen[$pattern])) == $pattern) {
					$weightB = $weight;
					break;
				}
			}

			if ($weightA == $weightB) {
				$weightA = substr(strrchr($a, ':'), 1);
				$weightB = substr(strrchr($b, ':'), 1);
			}
			return $weightA - $weightB;
		});

		$seqdetect = 0;
		// Filter empty lines before '@' block
		reset($lines);
		while (list($idx, $line) = each($lines)) {
			$weight = substr(strrchr($line, ':'), 1);
			$line = substr($line, 0, -1 * (strlen($line) - strrpos($line, ':')));
			if ($weight != $seqdetect) {
				prev($lines);
				break;
			}

			++$seqdetect;
		}
		while (list($idx, $line) = each($lines)) {
			$weight = substr(strrchr($line, ':'), 1);
			$line = substr($line, 0, -1 * (strlen($line) - strrpos($line, ':')));
			if (empty($line)) {
				unset($lines[$idx]);
			}
		}

		// Align tags
		$patterns = [
			'@param' => strlen('@param'),
			'@throws' => strlen('@throws'),
			'@return' => strlen('@return'),
			'@var' => strlen('@var'),
			'@type' => strlen('@type'),
		];
		$patternsColumns = [
			'@param' => 4,
			'@throws' => 2,
			'@return' => 2,
			'@var' => 4,
			'@type' => 4,
		];
		$maxColumn = [];

		foreach ($lines as $idx => $line) {
			foreach ($patterns as $pattern => $len) {
				if (strtolower(substr(ltrim($line), 0, $len)) == $pattern) {
					$words = explode(' ', $line);
					$i = 0;
					foreach ($words as $word) {
						if (!trim($word)) {
							continue;
						}
						$maxColumn[$i] = isset($maxColumn[$i]) ? max($maxColumn[$i], strlen($word)) : strlen($word);
						if (2 == $i) {
							break;
						}
						++$i;
					}
				}
			}
		}

		foreach ($lines as $idx => $line) {
			foreach ($patterns as $pattern => $len) {
				if (strtolower(substr(ltrim($line), 0, $len)) == $pattern) {
					$words = explode(' ', $line);
					$currentLine = '';
					$pad = 0;
					$columnCount = 0;
					$maxColumnCount = $patternsColumns[$pattern];
					foreach ($maxColumn as $rightMost) {
						while ((list(, $word) = each($words))) {
							if (trim($word)) {
								break;
							}
						}

						$currentLine .= $word;
						$pad += $rightMost + 1;
						$currentLine = str_pad($currentLine, $pad);
						++$columnCount;
						if ($columnCount == $maxColumnCount) {
							break;
						}
					}
					while ((list(, $word) = each($words))) {
						if (!trim($word)) {
							continue;
						}
						$currentLine .= $word . ' ';
					}
					$lines[$idx] = rtrim($currentLine);
				}
			}
		}

		// Space lines
		$lastGroup = null;
		foreach ($lines as $idx => $line) {
			if ('@' == substr(ltrim($line), 0, 1)) {
				$tag = strtolower(substr($line, 0, strpos($line, ' ')));
				if (isset($groups[$tag]) && $groups[$tag] != $lastGroup) {
					$lines[$idx] = (null !== $lastGroup ? $this->newLine . ' * ' : '') . $line;
					$lastGroup = $groups[$tag];
				}
			}
		}

		// Output
		$docBlock = '/**' . $this->newLine;
		foreach ($lines as $line) {
			$docBlock .= ' * ' . substr(rtrim($line), 0, strrpos($line, ':')) . $this->newLine;
		}
		$docBlock .= ' */';

		if ($isUTF8) {
			$docBlock = utf8_encode($docBlock);
		}

		return $docBlock;
	}
}