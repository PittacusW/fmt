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

class AlignDoubleArrow extends AdditionalPass {
	const ALIGNABLE_EQUAL = "\x2 EQUAL%d.%d.%d \x3";

	// level.levelentracecounter.counter

	public function __construct() {
		$this->strposFunc = 'strpos';
		$this->substrCountFunc = 'substr_count';

		if (function_exists('mb_strpos')) {
			$this->strposFunc = 'mb_strpos';
			$this->substrCountFunc = 'mb_substr_count';
		}
	}

	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_DOUBLE_ARROW])) {
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
		$maxContextCounter = [];

		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			switch ($id) {
			case ST_COMMA:
				if (!$this->hasLnAfter() && !$this->hasLnRightToken()) {
					if (!isset($levelEntranceCounter[$levelCounter])) {
						$levelEntranceCounter[$levelCounter] = 0;
					}
					if (!isset($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
						$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
						$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					}
					++$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
					$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = max($maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]], $contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]);
				} elseif ($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] > 1) {
					$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 1;
				}
				$this->appendCode($text);
				break;

			case T_DOUBLE_ARROW:
				if (isset($levelEntranceCounter[$levelCounter], $contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
					$this->appendCode(
						sprintf(
							self::ALIGNABLE_EQUAL,
							$levelCounter,
							$levelEntranceCounter[$levelCounter],
							$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]
						)
					);
				}

				$this->appendCode($text);
				break;

			case ST_PARENTHESES_OPEN:
			case ST_BRACKET_OPEN:
				++$levelCounter;
				if (!isset($levelEntranceCounter[$levelCounter])) {
					$levelEntranceCounter[$levelCounter] = 0;
				}
				++$levelEntranceCounter[$levelCounter];
				if (!isset($contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]])) {
					$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
					$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = 0;
				}
				++$contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]];
				$maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]] = max($maxContextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]], $contextCounter[$levelCounter][$levelEntranceCounter[$levelCounter]]);

				$this->appendCode($text);
				break;

			case ST_PARENTHESES_CLOSE:
			case ST_BRACKET_CLOSE:
				--$levelCounter;
				$this->appendCode($text);
				break;

			default:
				$this->appendCode($text);
				break;
			}
		}

		$this->align($maxContextCounter);

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getDescription() {
		return 'Vertically align T_DOUBLE_ARROW (=>).';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getExample() {
		return <<<'EOT'
<?php
$a = [
	1 => 1,
	22 => 22,
	333 => 333,
];

$a = [
	1   => 1,
	22  => 22,
	333 => 333,
];
?>
EOT;
	}

	protected function align($maxContextCounter) {
		foreach ($maxContextCounter as $level => $entrances) {
			foreach ($entrances as $entrance => $context) {
				for ($j = 0; $j <= $context; ++$j) {
					$placeholder = sprintf(self::ALIGNABLE_EQUAL, $level, $entrance, $j);
					if (false === $this->strpos($this->code, $placeholder)) {
						continue;
					}
					if (1 === $this->substrCount($this->code, $placeholder)) {
						$this->code = str_replace($placeholder, '', $this->code);
						continue;
					}

					$lines = explode($this->newLine, $this->code);
					$linesWithObjop = [];

					foreach ($lines as $idx => $line) {
						if (false !== $this->strpos($line, $placeholder)) {
							$linesWithObjop[] = $idx;
						}
					}

					$farthest = 0;
					foreach ($linesWithObjop as $idx) {
						$farthest = max($farthest, $this->strpos($lines[$idx], $placeholder));
					}
					foreach ($linesWithObjop as $idx) {
						$line = $lines[$idx];
						$current = $this->strpos($line, $placeholder);
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
	}

	private function strpos($code, $placeholder) {
		return call_user_func($this->strposFunc, $code, $placeholder);
	}

	private function substrCount($code, $placeholder) {
		return call_user_func($this->substrCountFunc, $code, $placeholder);
	}
}
