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

function showHelp($argv, $enableCache, $inPhar) {
	echo 'Usage: ' . $argv[0] . ' [-hv] [-o=FILENAME] [--config=FILENAME] ' . ($enableCache ? '[--cache[=FILENAME]] ' : '') . '[options] <target>', PHP_EOL;
	$options = [
		'--cache[=FILENAME]' => 'cache file. Default: ',
		'--cakephp' => 'Apply CakePHP coding style',
		'--config=FILENAME' => 'configuration file. Default: .phpfmt.ini',
		'--constructor=type' => 'analyse classes for attributes and generate constructor - camel, snake, golang',
		'--dry-run' => 'Runs the formatter without atually changing files; returns exit code 1 if changes would have been applied',
		'--enable_auto_align' => 'disable auto align of ST_EQUAL and T_DOUBLE_ARROW',
		'--exclude=pass1,passN,...' => 'disable specific passes',
		'--help-pass' => 'show specific information for one pass',
		'--ignore=PATTERN-1,PATTERN-N,...' => 'ignore file names whose names contain any PATTERN-N',
		'--indent_with_space=SIZE' => 'use spaces instead of tabs for indentation. Default 4',
		'--lint-before' => 'lint files before pretty printing (PHP must be declared in %PATH%/$PATH)',
		'--list' => 'list possible transformations',
		'--list-simple' => 'list possible transformations - greppable',
		'--no-backup' => 'no backup file (original.php~)',
		'--passes=pass1,passN,...' => 'call specific compiler pass',
		'--profile=NAME' => 'use one of profiles present in configuration file',
		'--psr' => 'activate PSR1 and PSR2 styles',
		'--psr1' => 'activate PSR1 style',
		'--psr1-naming' => 'activate PSR1 style - Section 3 and 4.3 - Class and method names case.',
		'--psr2' => 'activate PSR2 style',
		'--setters_and_getters=type' => 'analyse classes for attributes and generate setters and getters - camel, snake, golang',
		'--smart_linebreak_after_curly' => 'convert multistatement blocks into multiline blocks',
		'--visibility_order' => 'fixes visibiliy order for method in classes - PSR-2 4.2',
		'--yoda' => 'yoda-style comparisons',
		'-h, --help' => 'this help message',
		'-o=file' => 'output the formatted code to "file"',
		'-o=-' => 'output the formatted code to standard output',
		'-v' => 'verbose',
	];
	if ($inPhar) {
		$options['--selfupdate'] = 'self-update fmt.phar from Github';
		$options['--version'] = 'version';
	}
	$options['--cache[=FILENAME]'] .= (Cacher::DEFAULT_CACHE_FILENAME);
	if (!$enableCache) {
		unset($options['--cache[=FILENAME]']);
	}
	ksort($options);
	$maxLen = max(array_map(function ($v) {
		return strlen($v);
	}, array_keys($options)));
	foreach ($options as $k => $v) {
		echo '  ', str_pad($k, $maxLen), '  ', $v, PHP_EOL;
	}

	echo PHP_EOL, 'If <target> is "-", it reads from stdin', PHP_EOL;
}

$getoptLongOptions = [
	'cache::',
	'cakephp',
	'config:',
	'constructor:',
	'dry-run',
	'enable_auto_align',
	'exclude:',
	'help',
	'help-pass:',
	'ignore:',
	'indent_with_space::',
	'lint-before',
	'list',
	'list-simple',
	'no-backup',
	'oracleDB::',
	'passes:',
	'php2go',
	'profile:',
	'psr',
	'psr1',
	'psr1-naming',
	'psr2',
	'setters_and_getters:',
	'smart_linebreak_after_curly',
	'visibility_order',
	'yoda',
];
if ($inPhar) {
	$getoptLongOptions[] = 'selfupdate';
	$getoptLongOptions[] = 'version';
}
if (!$enableCache) {
	unset($getoptLongOptions['cache::']);
}
$opts = getopt(
	'ihvo:',
	$getoptLongOptions
);

if (isset($opts['list'])) {
	echo 'Usage: ', $argv[0], ' --help-pass=PASSNAME', PHP_EOL;
	$classes = get_declared_classes();
	$helpLines = [];
	foreach ($classes as $className) {
		if (is_subclass_of($className, 'AdditionalPass')) {
			$pass = new $className();
			$helpLines[] = ["\t- " . $className, $pass->getDescription()];
		}
	}
	echo tabwriter($helpLines);
	die();
}

if (isset($opts['list-simple'])) {
	$classes = get_declared_classes();
	$helpLines = [];
	foreach ($classes as $className) {
		if (is_subclass_of($className, 'AdditionalPass')) {
			$pass = new $className();
			$helpLines[] = [$className, $pass->getDescription()];
		}
	}
	echo tabwriter($helpLines);
	die();
}
if (isset($opts['selfupdate'])) {
	selfupdate($argv, $inPhar);
}
if (isset($opts['version'])) {
	if ($inPhar) {
		echo $argv[0], ' ', VERSION, PHP_EOL;
	}
	exit(0);
}
if (isset($opts['config'])) {
	$argv = extractFromArgv($argv, 'config');

	if ('scan' == $opts['config']) {
		$cfgfn = getcwd() . DIRECTORY_SEPARATOR . '.phpfmt.ini';
		$lastcfgfn = '';
		fwrite(STDERR, 'Scanning for configuration file...');
		while (!is_file($cfgfn) && $lastcfgfn != $cfgfn) {
			$lastcfgfn = $cfgfn;
			$cfgfn = dirname(dirname($cfgfn)) . DIRECTORY_SEPARATOR . '.phpfmt.ini';
		}
		$opts['config'] = $cfgfn;
		if (file_exists($opts['config']) && is_file($opts['config'])) {
			fwrite(STDERR, $opts['config']);
			$iniOpts = parse_ini_file($opts['config'], true);
			if (!empty($iniOpts)) {
				$opts += $iniOpts;
			}
		}
		fwrite(STDERR, PHP_EOL);
	} else {
		if (!file_exists($opts['config']) || !is_file($opts['config'])) {
			fwrite(STDERR, 'Custom configuration not file found' . PHP_EOL);
			exit(255);
		}
		$iniOpts = parse_ini_file($opts['config'], true);
		if (!empty($iniOpts)) {
			$opts += $iniOpts;
		}
	}
} elseif (file_exists(getcwd() . DIRECTORY_SEPARATOR . '.phpfmt.ini') && is_file(getcwd() . DIRECTORY_SEPARATOR . '.phpfmt.ini')) {
	fwrite(STDERR, 'Configuration file found' . PHP_EOL);
	$iniOpts = parse_ini_file(getcwd() . DIRECTORY_SEPARATOR . '.phpfmt.ini', true);
	if (isset($opts['profile'])) {
		$argv = extractFromArgv($argv, 'profile');
		$profile = &$iniOpts[$opts['profile']];
		if (isset($profile)) {
			$iniOpts = $profile;
		}
	}
	$opts = array_merge($iniOpts, $opts);
}
if (isset($opts['h']) || isset($opts['help'])) {
	showHelp($argv, $enableCache, $inPhar);
	exit(0);
}

if (isset($opts['help-pass'])) {
	$optPass = $opts['help-pass'];
	if (class_exists($optPass) && method_exists($optPass, 'getDescription')) {
		$pass = new $optPass();
		echo $argv[0], ': "', $optPass, '" - ', $pass->getDescription(), PHP_EOL, PHP_EOL;
		echo 'Example:', PHP_EOL, $pass->getExample(), PHP_EOL;
	} else {
		echo $argv[0], ': Core pass.';
	}
	die();
}

$cache = null;
$cache_fn = null;
if ($enableCache && isset($opts['cache'])) {
	$argv = extractFromArgv($argv, 'cache');
	$cache_fn = $opts['cache'];
	$cache = new Cache($cache_fn);
	fwrite(STDERR, 'Using cache ...' . PHP_EOL);
} elseif (!$enableCache) {
	$cache = new Cache();
}

$backup = true;
if (isset($opts['no-backup'])) {
	$argv = extractFromArgv($argv, 'no-backup');
	$backup = false;
}

$dryRun = false;
if (isset($opts['dry-run'])) {
	$argv = extractFromArgv($argv, 'dry-run');
	$dryRun = true;
}

$ignore_list = null;
if (isset($opts['ignore'])) {
	$argv = extractFromArgv($argv, 'ignore');
	$ignore_list = array_map(function ($v) {
		return trim($v);
	}, explode(',', $opts['ignore']));
}

$lintBefore = false;
if (isset($opts['lint-before'])) {
	$argv = extractFromArgv($argv, 'lint-before');
	$lintBefore = true;
}

$fmt = new CodeFormatter();
if (isset($opts['setters_and_getters'])) {
	$argv = extractFromArgv($argv, 'setters_and_getters');
	$fmt->enablePass('SettersAndGettersPass', $opts['setters_and_getters']);
}

if (isset($opts['constructor'])) {
	$argv = extractFromArgv($argv, 'constructor');
	$fmt->enablePass('ConstructorPass', $opts['constructor']);
}

if (isset($opts['oracleDB'])) {
	$argv = extractFromArgv($argv, 'oracleDB');

	if ('scan' == $opts['oracleDB']) {
		$oracle = getcwd() . DIRECTORY_SEPARATOR . 'oracle.sqlite';
		$lastoracle = '';
		while (!is_file($oracle) && $lastoracle != $oracle) {
			$lastoracle = $oracle;
			$oracle = dirname(dirname($oracle)) . DIRECTORY_SEPARATOR . 'oracle.sqlite';
		}
		$opts['oracleDB'] = $oracle;
		fwrite(STDERR, PHP_EOL);
	}

	if (file_exists($opts['oracleDB']) && is_file($opts['oracleDB'])) {
		$fmt->enablePass('AutoImportPass', $opts['oracleDB']);
	}
}

if (isset($opts['smart_linebreak_after_curly'])) {
	$fmt->enablePass('SmartLnAfterCurlyOpen');
	$argv = extractFromArgv($argv, 'smart_linebreak_after_curly');
}

if (isset($opts['yoda'])) {
	$fmt->enablePass('YodaComparisons');
	$argv = extractFromArgv($argv, 'yoda');
}

if (isset($opts['enable_auto_align'])) {
	$fmt->enablePass('AlignEquals');
	$fmt->enablePass('AlignDoubleArrow');
	$argv = extractFromArgv($argv, 'enable_auto_align');
}

if (isset($opts['psr'])) {
	PsrDecorator::decorate($fmt);
	$argv = extractFromArgv($argv, 'psr');
}

if (isset($opts['psr1'])) {
	PsrDecorator::PSR1($fmt);
	$argv = extractFromArgv($argv, 'psr1');
}

if (isset($opts['psr1-naming'])) {
	PsrDecorator::PSR1Naming($fmt);
	$argv = extractFromArgv($argv, 'psr1-naming');
}

if (isset($opts['psr2'])) {
	PsrDecorator::PSR2($fmt);
	$argv = extractFromArgv($argv, 'psr2');
}

if (isset($opts['indent_with_space'])) {
	$fmt->enablePass('PSR2IndentWithSpace', $opts['indent_with_space']);
	$argv = extractFromArgv($argv, 'indent_with_space');
}

if ((isset($opts['psr1']) || isset($opts['psr2']) || isset($opts['psr'])) && isset($opts['enable_auto_align'])) {
	$fmt->enablePass('PSR2AlignObjOp');
}

if (isset($opts['visibility_order'])) {
	$fmt->enablePass('PSR2ModifierVisibilityStaticOrder');
	$argv = extractFromArgv($argv, 'visibility_order');
}

if (isset($opts['passes'])) {
	$optPasses = array_map(function ($v) {
		return trim($v);
	}, explode(',', $opts['passes']));
	foreach ($optPasses as $optPass) {
		$fmt->enablePass($optPass);
	}
	$argv = extractFromArgv($argv, 'passes');
}

if (isset($opts['cakephp'])) {
	$fmt->enablePass('CakePHPStyle');
	$argv = extractFromArgv($argv, 'cakephp');
}

if (isset($opts['php2go'])) {
	Php2GoDecorator::decorate($fmt);
	$argv = extractFromArgv($argv, 'php2go');
}

if (isset($opts['exclude'])) {
	$passesNames = explode(',', $opts['exclude']);
	foreach ($passesNames as $passName) {
		$fmt->disablePass(trim($passName));
	}
	$argv = extractFromArgv($argv, 'exclude');
}

if (isset($opts['v'])) {
	$argv = extractFromArgvShort($argv, 'v');
	fwrite(STDERR, 'Used passes: ' . implode(', ', $fmt->getPassesNames()) . PHP_EOL);
}

if (isset($opts['i'])) {
	echo 'php.tools fmt.php interactive mode.', PHP_EOL;
	echo 'no <?php is necessary', PHP_EOL;
	echo 'type a lone "." to finish input.', PHP_EOL;
	echo 'type "quit" to finish.', PHP_EOL;
	while (true) {
		$str = '';
		do {
			$line = readline('> ');
			$str .= $line;
		} while (!('.' == $line || 'quit' == $line));
		if ('quit' == $line) {
			exit(0);
		}
		readline_add_history(substr($str, 0, -1));
		echo $fmt->formatCode('<?php ' . substr($str, 0, -1)), PHP_EOL;
	}
} elseif (isset($opts['o'])) {
	$argv = extractFromArgvShort($argv, 'o');
	if ('-' == $opts['o'] && '-' == $argv[1]) {
		echo $fmt->formatCode(file_get_contents('php://stdin'));
		exit(0);
	}
	if ($inPhar) {
		if (!file_exists($argv[1])) {
			$argv[1] = getcwd() . DIRECTORY_SEPARATOR . $argv[1];
		}
	}
	if (!is_file($argv[1])) {
		fwrite(STDERR, 'File not found: ' . $argv[1] . PHP_EOL);
		exit(255);
	}
	if ('-' == $opts['o']) {
		echo $fmt->formatCode(file_get_contents($argv[1]));
		exit(0);
	}
	$argv = array_values($argv);
	file_put_contents($opts['o'], $fmt->formatCode(file_get_contents($argv[1])));
} elseif (isset($argv[1])) {
	if ('-' == $argv[1]) {
		echo $fmt->formatCode(file_get_contents('php://stdin'));
		exit(0);
	}
	$fileNotFound = false;
	$start = microtime(true);
	fwrite(STDERR, 'Formatting ...' . PHP_EOL);
	$missingFiles = [];
	$fileCount = 0;

	$cacheHitCount = 0;
	$workers = 4;

	$hasFnSeparator = false;

	// Used with dry-run to flag if any files would have been changed
	$filesChanged = false;

	for ($j = 1; $j < $argc; ++$j) {
		$arg = &$argv[$j];
		if (!isset($arg)) {
			continue;
		}
		if ('--' == $arg) {
			$hasFnSeparator = true;
			continue;
		}
		if ($inPhar && !file_exists($arg)) {
			$arg = getcwd() . DIRECTORY_SEPARATOR . $arg;
		}
		if (is_file($arg)) {
			$file = $arg;
			if ($lintBefore && !lint($file)) {
				fwrite(STDERR, 'Error lint:' . $file . PHP_EOL);
				continue;
			}
			++$fileCount;
			fwrite(STDERR, '.');
			$fileContents = file_get_contents($file);
			$formattedCode = $fmt->formatCode($fileContents);
			if ($dryRun) {
				if ($fileContents !== $formattedCode) {
					$filesChanged = true;
				}
			} else {
				file_put_contents($file . '-tmp', $formattedCode);
				$oldchmod = fileperms($file);
				rename($file . '-tmp', $file);
				chmod($file, $oldchmod);
			}
		} elseif (is_dir($arg)) {
			fwrite(STDERR, $arg . PHP_EOL);

			$target_dir = $arg;
			$dir = new RecursiveDirectoryIterator($target_dir);
			$it = new RecursiveIteratorIterator($dir);
			$files = new RegexIterator($it, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

			if ($concurrent) {
				$chn = make_channel();
				$chn_done = make_channel();
				if ($concurrent) {
					fwrite(STDERR, 'Starting ' . $workers . ' workers ...' . PHP_EOL);
				}
				for ($i = 0; $i < $workers; ++$i) {
					cofunc(function ($fmt, $backup, $cache_fn, $chn, $chn_done, $lintBefore, $dryRun) {
						$cache = new Cache($cache_fn);
						$cacheHitCount = 0;
						$cache_miss_count = 0;
						$filesChanged = false;
						while (true) {
							$msg = $chn->out();
							if (null === $msg) {
								break;
							}
							$target_dir = $msg['target_dir'];
							$file = $msg['file'];
							if (empty($file)) {
								continue;
							}
							if ($lintBefore && !lint($file)) {
								fwrite(STDERR, 'Error lint:' . $file . PHP_EOL);
								continue;
							}

							$content = $cache->is_changed($target_dir, $file);
							if (false === $content) {
								++$cacheHitCount;
								continue;
							}

							++$cache_miss_count;
							$fmtCode = $fmt->formatCode($content);
							if (null !== $cache) {
								$cache->upsert($target_dir, $file, $fmtCode);
							}
							if ($dryRun) {
								if ($fmtCode !== $content) {
									$filesChanged = true;
								}
							} else {
								file_put_contents($file . '-tmp', $fmtCode);
								$oldchmod = fileperms($file);
								$backup && rename($file, $file . '~');
								rename($file . '-tmp', $file);
								chmod($file, $oldchmod);
							}
						}
						$chn_done->in([$cacheHitCount, $cache_miss_count, $filesChanged]);
					}, $fmt, $backup, $cache_fn, $chn, $chn_done, $lintBefore, $dryRun);
				}
			}

			$progress = new \Symfony\Component\Console\Helper\ProgressBar(
				new \Symfony\Component\Console\Output\StreamOutput(fopen('php://stderr', 'w')),
				sizeof(iterator_to_array($files))
			);
			$progress->start();
			foreach ($files as $file) {
				$progress->advance();
				$file = $file[0];
				if (null !== $ignore_list) {
					foreach ($ignore_list as $pattern) {
						if (false !== strpos($file, $pattern)) {
							continue 2;
						}
					}
				}

				++$fileCount;
				if ($concurrent) {
					$chn->in([
						'target_dir' => $target_dir,
						'file' => $file,
					]);
				} else {
					if (0 == ($fileCount % 20)) {
						fwrite(STDERR, ' ' . $fileCount . PHP_EOL);
					}
					$content = $cache->is_changed($target_dir, $file);
					if (false === $content) {
						++$fileCount;
						++$cacheHitCount;
						continue;
					}
					if ($lintBefore && !lint($file)) {
						fwrite(STDERR, 'Error lint:' . $file . PHP_EOL);
						continue;
					}
					$fmtCode = $fmt->formatCode($content);
					fwrite(STDERR, '.');
					if (null !== $cache) {
						$cache->upsert($target_dir, $file, $fmtCode);
					}
					if ($dryRun) {
						if ($fmtCode !== $content) {
							$filesChanged = true;
						}
					} else {
						file_put_contents($file . '-tmp', $fmtCode);
						$oldchmod = fileperms($file);
						$backup && rename($file, $file . '~');
						rename($file . '-tmp', $file);
						chmod($file, $oldchmod);
					}
				}
			}
			if ($concurrent) {
				for ($i = 0; $i < $workers; ++$i) {
					$chn->in(null);
				}
				for ($i = 0; $i < $workers; ++$i) {
					list($cache_hit, $cache_miss, $filesChanged) = $chn_done->out();
					$cacheHitCount += $cache_hit;
				}
				$chn_done->close();
				$chn->close();
			}
			$progress->finish();
			fwrite(STDERR, PHP_EOL);

			continue;
		} elseif (
			!is_file($arg) &&
			('--' != substr($arg, 0, 2) || $hasFnSeparator)
		) {
			$fileNotFound = true;
			$missingFiles[] = $arg;
			fwrite(STDERR, '!');
		}
		if (0 == ($fileCount % 20)) {
			fwrite(STDERR, ' ' . $fileCount . PHP_EOL);
		}
	}
	fwrite(STDERR, PHP_EOL);
	if (null !== $cache) {
		fwrite(STDERR, ' ' . $cacheHitCount . ' files untouched (cache hit)' . PHP_EOL);
	}
	fwrite(STDERR, ' ' . $fileCount . ' files total' . PHP_EOL);
	fwrite(STDERR, 'Took ' . round(microtime(true) - $start, 2) . 's' . PHP_EOL);
	if (sizeof($missingFiles)) {
		fwrite(STDERR, 'Files not found: ' . PHP_EOL);
		foreach ($missingFiles as $file) {
			fwrite(STDERR, "\t - " . $file . PHP_EOL);
		}
	}
	if ($dryRun && $filesChanged) {
		exit(1);
	}
	if ($fileNotFound) {
		exit(255);
	}
} else {
	showHelp($argv, $enableCache, $inPhar);
}
exit(0);
