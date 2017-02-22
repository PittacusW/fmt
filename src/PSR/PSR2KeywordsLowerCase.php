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

final class PSR2KeywordsLowerCase extends FormatterPass {
	private static $reservedWords = [
		'__halt_compiler' => 1,
		'abstract' => 1, 'and' => 1, 'array' => 1, 'as' => 1,
		'break' => 1,
		'callable' => 1, 'case' => 1, 'catch' => 1, 'class' => 1, 'clone' => 1, 'const' => 1, 'continue' => 1,
		'declare' => 1, 'default' => 1, 'die' => 1, 'do' => 1,
		'echo' => 1, 'else' => 1, 'elseif' => 1, 'empty' => 1, 'enddeclare' => 1, 'endfor' => 1, 'endforeach' => 1, 'endif' => 1, 'endswitch' => 1, 'endwhile' => 1, 'eval' => 1, 'exit' => 1, 'extends' => 1,
		'final' => 1, 'for' => 1, 'foreach' => 1, 'function' => 1,
		'global' => 1, 'goto' => 1,
		'if' => 1, 'implements' => 1, 'include' => 1, 'include_once' => 1, 'instanceof' => 1, 'insteadof' => 1, 'interface' => 1, 'isset' => 1,
		'list' => 1,
		'namespace' => 1, 'new' => 1,
		'or' => 1,
		'print' => 1, 'private' => 1, 'protected' => 1, 'public' => 1,
		'require' => 1, 'require_once' => 1, 'return' => 1,
		'static' => 1, 'switch' => 1,
		'throw' => 1, 'trait' => 1, 'try' => 1,
		'unset' => 1, 'use' => 1, 'var' => 1,
		'while' => 1, 'xor' => 1,
	];

	public function candidate($source, $foundTokens) {
		return true;
	}

	public function format($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if (
				T_WHITESPACE == $id ||
				T_VARIABLE == $id ||
				T_INLINE_HTML == $id ||
				T_COMMENT == $id ||
				T_DOC_COMMENT == $id ||
				T_CONSTANT_ENCAPSED_STRING == $id
			) {
				$this->appendCode($text);
				continue;
			}

			if (
				T_STRING == $id
				&& $this->leftUsefulTokenIs([T_DOUBLE_COLON, T_OBJECT_OPERATOR])
			) {
				$this->appendCode($text);
				continue;
			}

			if (T_START_HEREDOC == $id) {
				$this->appendCode($text);
				$this->printUntil(ST_SEMI_COLON);
				continue;
			}
			if (ST_QUOTE == $id) {
				$this->appendCode($text);
				$this->printUntilTheEndOfString();
				continue;
			}
			$lcText = strtolower($text);
			if (
				(
					('true' === $lcText || 'false' === $lcText || 'null' === $lcText) &&
					!$this->leftUsefulTokenIs([
						T_NS_SEPARATOR, T_AS, T_CLASS, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF, T_INTERFACE, T_NEW, T_NS_SEPARATOR, T_PAAMAYIM_NEKUDOTAYIM, T_USE, T_TRAIT, T_INSTEADOF, T_CONST,
					]) &&
					!$this->rightUsefulTokenIs([
						T_NS_SEPARATOR, T_AS, T_CLASS, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF, T_INTERFACE, T_NEW, T_NS_SEPARATOR, T_PAAMAYIM_NEKUDOTAYIM, T_USE, T_TRAIT, T_INSTEADOF, T_CONST,
					])
				) ||
				isset(static::$reservedWords[$lcText])
			) {
				$text = $lcText;
			}
			$this->appendCode($text);
		}

		return $this->code;
	}
}