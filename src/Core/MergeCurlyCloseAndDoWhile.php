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

final class MergeCurlyCloseAndDoWhile extends FormatterPass {
	public function candidate($source, $foundTokens) {
		if (isset($foundTokens[T_WHILE], $foundTokens[T_DO])) {
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
			case T_WHILE:
				$str = $text;
				while (list($index, $token) = each($this->tkns)) {
					list($id, $text) = $this->getToken($token);
					$this->ptr = $index;
					$str .= $text;
					if (
						ST_CURLY_OPEN == $id ||
						ST_COLON == $id ||
						(ST_SEMI_COLON == $id && (ST_SEMI_COLON == $ptId || ST_CURLY_OPEN == $ptId || T_COMMENT == $ptId || T_DOC_COMMENT == $ptId))
					) {
						$this->appendCode($str);
						break;
					} elseif (ST_SEMI_COLON == $id && !(ST_SEMI_COLON == $ptId || ST_CURLY_OPEN == $ptId || T_COMMENT == $ptId || T_DOC_COMMENT == $ptId)) {
						$this->rtrimAndAppendCode($str);
						break;
					}
				}
				break;

			case T_WHITESPACE:
				$this->appendCode($text);
				break;

			default:
				$ptId = $id;
				$this->appendCode($text);
				break;
			}
		}
		return $this->code;
	}
}
