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


	


namespace Symfony\Component\Console\Formatter{


interface OutputFormatterInterface
{
    
    public function setDecorated($decorated);

    
    public function isDecorated();

    
    public function setStyle($name, OutputFormatterStyleInterface $style);

    
    public function hasStyle($name);

    
    public function getStyle($name);

    
    public function format($message);
}

}

	


namespace Symfony\Component\Console\Helper{


interface HelperInterface
{
    
    public function setHelperSet(HelperSet $helperSet = null);

    
    public function getHelperSet();

    
    public function getName();
}

}

	


namespace Symfony\Component\Console\Helper{

use Symfony\Component\Console\Formatter\OutputFormatterInterface;


abstract class Helper implements HelperInterface
{
    protected $helperSet = null;

    
    public function setHelperSet(HelperSet $helperSet = null)
    {
        $this->helperSet = $helperSet;
    }

    
    public function getHelperSet()
    {
        return $this->helperSet;
    }

    
    public static function strlen($string)
    {
        if (!function_exists('mb_strwidth')) {
            return strlen($string);
        }

        if (false === $encoding = mb_detect_encoding($string)) {
            return strlen($string);
        }

        return mb_strwidth($string, $encoding);
    }

    public static function formatTime($secs)
    {
        static $timeFormats = array(
            array(0, '< 1 sec'),
            array(2, '1 sec'),
            array(59, 'secs', 1),
            array(60, '1 min'),
            array(3600, 'mins', 60),
            array(5400, '1 hr'),
            array(86400, 'hrs', 3600),
            array(129600, '1 day'),
            array(604800, 'days', 86400),
        );

        foreach ($timeFormats as $format) {
            if ($secs >= $format[0]) {
                continue;
            }

            if (2 == count($format)) {
                return $format[1];
            }

            return ceil($secs / $format[2]).' '.$format[1];
        }
    }

    public static function formatMemory($memory)
    {
        if ($memory >= 1024 * 1024 * 1024) {
            return sprintf('%.1f GiB', $memory / 1024 / 1024 / 1024);
        }

        if ($memory >= 1024 * 1024) {
            return sprintf('%.1f MiB', $memory / 1024 / 1024);
        }

        if ($memory >= 1024) {
            return sprintf('%d KiB', $memory / 1024);
        }

        return sprintf('%d B', $memory);
    }

    public static function strlenWithoutDecoration(OutputFormatterInterface $formatter, $string)
    {
        $isDecorated = $formatter->isDecorated();
        $formatter->setDecorated(false);
                $string = $formatter->format($string);
                $string = preg_replace("/\033\[[^m]*m/", '', $string);
        $formatter->setDecorated($isDecorated);

        return self::strlen($string);
    }
}

}

	


namespace Symfony\Component\Console\Formatter{


class OutputFormatterStyleStack
{
    
    private $styles;

    
    private $emptyStyle;

    
    public function __construct(OutputFormatterStyleInterface $emptyStyle = null)
    {
        $this->emptyStyle = $emptyStyle ?: new OutputFormatterStyle();
        $this->reset();
    }

    
    public function reset()
    {
        $this->styles = array();
    }

    
    public function push(OutputFormatterStyleInterface $style)
    {
        $this->styles[] = $style;
    }

    
    public function pop(OutputFormatterStyleInterface $style = null)
    {
        if (empty($this->styles)) {
            return $this->emptyStyle;
        }

        if (null === $style) {
            return array_pop($this->styles);
        }

        foreach (array_reverse($this->styles, true) as $index => $stackedStyle) {
            if ($style->apply('') === $stackedStyle->apply('')) {
                $this->styles = array_slice($this->styles, 0, $index);

                return $stackedStyle;
            }
        }

        throw new \InvalidArgumentException('Incorrectly nested style tag found.');
    }

    
    public function getCurrent()
    {
        if (empty($this->styles)) {
            return $this->emptyStyle;
        }

        return $this->styles[count($this->styles) - 1];
    }

    
    public function setEmptyStyle(OutputFormatterStyleInterface $emptyStyle)
    {
        $this->emptyStyle = $emptyStyle;

        return $this;
    }

    
    public function getEmptyStyle()
    {
        return $this->emptyStyle;
    }
}

}

	


namespace Symfony\Component\Console\Formatter{


interface OutputFormatterStyleInterface
{
    
    public function setForeground($color = null);

    
    public function setBackground($color = null);

    
    public function setOption($option);

    
    public function unsetOption($option);

    
    public function setOptions(array $options);

    
    public function apply($text);
}

}

	


namespace Symfony\Component\Console\Formatter{


class OutputFormatterStyle implements OutputFormatterStyleInterface
{
    private static $availableForegroundColors = array(
        'black' => array('set' => 30, 'unset' => 39),
        'red' => array('set' => 31, 'unset' => 39),
        'green' => array('set' => 32, 'unset' => 39),
        'yellow' => array('set' => 33, 'unset' => 39),
        'blue' => array('set' => 34, 'unset' => 39),
        'magenta' => array('set' => 35, 'unset' => 39),
        'cyan' => array('set' => 36, 'unset' => 39),
        'white' => array('set' => 37, 'unset' => 39),
        'default' => array('set' => 39, 'unset' => 39),
    );
    private static $availableBackgroundColors = array(
        'black' => array('set' => 40, 'unset' => 49),
        'red' => array('set' => 41, 'unset' => 49),
        'green' => array('set' => 42, 'unset' => 49),
        'yellow' => array('set' => 43, 'unset' => 49),
        'blue' => array('set' => 44, 'unset' => 49),
        'magenta' => array('set' => 45, 'unset' => 49),
        'cyan' => array('set' => 46, 'unset' => 49),
        'white' => array('set' => 47, 'unset' => 49),
        'default' => array('set' => 49, 'unset' => 49),
    );
    private static $availableOptions = array(
        'bold' => array('set' => 1, 'unset' => 22),
        'underscore' => array('set' => 4, 'unset' => 24),
        'blink' => array('set' => 5, 'unset' => 25),
        'reverse' => array('set' => 7, 'unset' => 27),
        'conceal' => array('set' => 8, 'unset' => 28),
    );

    private $foreground;
    private $background;
    private $options = array();

    
    public function __construct($foreground = null, $background = null, array $options = array())
    {
        if (null !== $foreground) {
            $this->setForeground($foreground);
        }
        if (null !== $background) {
            $this->setBackground($background);
        }
        if (count($options)) {
            $this->setOptions($options);
        }
    }

    
    public function setForeground($color = null)
    {
        if (null === $color) {
            $this->foreground = null;

            return;
        }

        if (!isset(static::$availableForegroundColors[$color])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid foreground color specified: "%s". Expected one of (%s)',
                $color,
                implode(', ', array_keys(static::$availableForegroundColors))
            ));
        }

        $this->foreground = static::$availableForegroundColors[$color];
    }

    
    public function setBackground($color = null)
    {
        if (null === $color) {
            $this->background = null;

            return;
        }

        if (!isset(static::$availableBackgroundColors[$color])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid background color specified: "%s". Expected one of (%s)',
                $color,
                implode(', ', array_keys(static::$availableBackgroundColors))
            ));
        }

        $this->background = static::$availableBackgroundColors[$color];
    }

    
    public function setOption($option)
    {
        if (!isset(static::$availableOptions[$option])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid option specified: "%s". Expected one of (%s)',
                $option,
                implode(', ', array_keys(static::$availableOptions))
            ));
        }

        if (!in_array(static::$availableOptions[$option], $this->options)) {
            $this->options[] = static::$availableOptions[$option];
        }
    }

    
    public function unsetOption($option)
    {
        if (!isset(static::$availableOptions[$option])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid option specified: "%s". Expected one of (%s)',
                $option,
                implode(', ', array_keys(static::$availableOptions))
            ));
        }

        $pos = array_search(static::$availableOptions[$option], $this->options);
        if (false !== $pos) {
            unset($this->options[$pos]);
        }
    }

    
    public function setOptions(array $options)
    {
        $this->options = array();

        foreach ($options as $option) {
            $this->setOption($option);
        }
    }

    
    public function apply($text)
    {
        $setCodes = array();
        $unsetCodes = array();

        if (null !== $this->foreground) {
            $setCodes[] = $this->foreground['set'];
            $unsetCodes[] = $this->foreground['unset'];
        }
        if (null !== $this->background) {
            $setCodes[] = $this->background['set'];
            $unsetCodes[] = $this->background['unset'];
        }
        if (count($this->options)) {
            foreach ($this->options as $option) {
                $setCodes[] = $option['set'];
                $unsetCodes[] = $option['unset'];
            }
        }

        if (0 === count($setCodes)) {
            return $text;
        }

        return sprintf("\033[%sm%s\033[%sm", implode(';', $setCodes), $text, implode(';', $unsetCodes));
    }
}

}

	


namespace Symfony\Component\Console\Formatter{


class OutputFormatter implements OutputFormatterInterface
{
    private $decorated;
    private $styles = array();
    private $styleStack;

    
    public static function escape($text)
    {
        return preg_replace('/([^\\\\]?)</', '$1\\<', $text);
    }

    
    public function __construct($decorated = false, array $styles = array())
    {
        $this->decorated = (bool) $decorated;

        $this->setStyle('error', new OutputFormatterStyle('white', 'red'));
        $this->setStyle('info', new OutputFormatterStyle('green'));
        $this->setStyle('comment', new OutputFormatterStyle('yellow'));
        $this->setStyle('question', new OutputFormatterStyle('black', 'cyan'));

        foreach ($styles as $name => $style) {
            $this->setStyle($name, $style);
        }

        $this->styleStack = new OutputFormatterStyleStack();
    }

    
    public function setDecorated($decorated)
    {
        $this->decorated = (bool) $decorated;
    }

    
    public function isDecorated()
    {
        return $this->decorated;
    }

    
    public function setStyle($name, OutputFormatterStyleInterface $style)
    {
        $this->styles[strtolower($name)] = $style;
    }

    
    public function hasStyle($name)
    {
        return isset($this->styles[strtolower($name)]);
    }

    
    public function getStyle($name)
    {
        if (!$this->hasStyle($name)) {
            throw new \InvalidArgumentException(sprintf('Undefined style: %s', $name));
        }

        return $this->styles[strtolower($name)];
    }

    
    public function format($message)
    {
        $message = (string) $message;
        $offset = 0;
        $output = '';
        $tagRegex = '[a-z][a-z0-9_=;-]*';
        preg_match_all("#<(($tagRegex) | /($tagRegex)?)>#ix", $message, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $i => $match) {
            $pos = $match[1];
            $text = $match[0];

            if (0 != $pos && '\\' == $message[$pos - 1]) {
                continue;
            }

                        $output .= $this->applyCurrentStyle(substr($message, $offset, $pos - $offset));
            $offset = $pos + strlen($text);

                        if ($open = '/' != $text[1]) {
                $tag = $matches[1][$i][0];
            } else {
                $tag = isset($matches[3][$i][0]) ? $matches[3][$i][0] : '';
            }

            if (!$open && !$tag) {
                                $this->styleStack->pop();
            } elseif (false === $style = $this->createStyleFromString(strtolower($tag))) {
                $output .= $this->applyCurrentStyle($text);
            } elseif ($open) {
                $this->styleStack->push($style);
            } else {
                $this->styleStack->pop($style);
            }
        }

        $output .= $this->applyCurrentStyle(substr($message, $offset));

        return str_replace('\\<', '<', $output);
    }

    
    public function getStyleStack()
    {
        return $this->styleStack;
    }

    
    private function createStyleFromString($string)
    {
        if (isset($this->styles[$string])) {
            return $this->styles[$string];
        }

        if (!preg_match_all('/([^=]+)=([^;]+)(;|$)/', strtolower($string), $matches, PREG_SET_ORDER)) {
            return false;
        }

        $style = new OutputFormatterStyle();
        foreach ($matches as $match) {
            array_shift($match);

            if ('fg' == $match[0]) {
                $style->setForeground($match[1]);
            } elseif ('bg' == $match[0]) {
                $style->setBackground($match[1]);
            } else {
                try {
                    $style->setOption($match[1]);
                } catch (\InvalidArgumentException $e) {
                    return false;
                }
            }
        }

        return $style;
    }

    
    private function applyCurrentStyle($text)
    {
        return $this->isDecorated() && strlen($text) > 0 ? $this->styleStack->getCurrent()->apply($text) : $text;
    }
}

}

	


namespace Symfony\Component\Console\Output{

use Symfony\Component\Console\Formatter\OutputFormatterInterface;


interface OutputInterface
{
    const VERBOSITY_QUIET = 0;
    const VERBOSITY_NORMAL = 1;
    const VERBOSITY_VERBOSE = 2;
    const VERBOSITY_VERY_VERBOSE = 3;
    const VERBOSITY_DEBUG = 4;

    const OUTPUT_NORMAL = 0;
    const OUTPUT_RAW = 1;
    const OUTPUT_PLAIN = 2;

    
    public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL);

    
    public function writeln($messages, $type = self::OUTPUT_NORMAL);

    
    public function setVerbosity($level);

    
    public function getVerbosity();

    
    public function setDecorated($decorated);

    
    public function isDecorated();

    
    public function setFormatter(OutputFormatterInterface $formatter);

    
    public function getFormatter();
}

}

	


namespace Symfony\Component\Console\Output{


interface ConsoleOutputInterface extends OutputInterface
{
    
    public function getErrorOutput();

    
    public function setErrorOutput(OutputInterface $error);
}

}

	


namespace Symfony\Component\Console\Output{

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;


abstract class Output implements OutputInterface
{
    private $verbosity;
    private $formatter;

    
    public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = false, OutputFormatterInterface $formatter = null)
    {
        $this->verbosity = null === $verbosity ? self::VERBOSITY_NORMAL : $verbosity;
        $this->formatter = $formatter ?: new OutputFormatter();
        $this->formatter->setDecorated($decorated);
    }

    
    public function setFormatter(OutputFormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }

    
    public function getFormatter()
    {
        return $this->formatter;
    }

    
    public function setDecorated($decorated)
    {
        $this->formatter->setDecorated($decorated);
    }

    
    public function isDecorated()
    {
        return $this->formatter->isDecorated();
    }

    
    public function setVerbosity($level)
    {
        $this->verbosity = (int) $level;
    }

    
    public function getVerbosity()
    {
        return $this->verbosity;
    }

    public function isQuiet()
    {
        return self::VERBOSITY_QUIET === $this->verbosity;
    }

    public function isVerbose()
    {
        return self::VERBOSITY_VERBOSE <= $this->verbosity;
    }

    public function isVeryVerbose()
    {
        return self::VERBOSITY_VERY_VERBOSE <= $this->verbosity;
    }

    public function isDebug()
    {
        return self::VERBOSITY_DEBUG <= $this->verbosity;
    }

    
    public function writeln($messages, $type = self::OUTPUT_NORMAL)
    {
        $this->write($messages, true, $type);
    }

    
    public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL)
    {
        if (self::VERBOSITY_QUIET === $this->verbosity) {
            return;
        }

        $messages = (array) $messages;

        foreach ($messages as $message) {
            switch ($type) {
                case OutputInterface::OUTPUT_NORMAL:
                    $message = $this->formatter->format($message);
                    break;
                case OutputInterface::OUTPUT_RAW:
                    break;
                case OutputInterface::OUTPUT_PLAIN:
                    $message = strip_tags($this->formatter->format($message));
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unknown output type given (%s)', $type));
            }

            $this->doWrite($message, $newline);
        }
    }

    
    abstract protected function doWrite($message, $newline);
}

}

	


namespace Symfony\Component\Console\Output{

use Symfony\Component\Console\Formatter\OutputFormatterInterface;


class StreamOutput extends Output
{
    private $stream;

    
    public function __construct($stream, $verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null)
    {
        if (!is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw new \InvalidArgumentException('The StreamOutput class needs a stream as its first argument.');
        }

        $this->stream = $stream;

        if (null === $decorated) {
            $decorated = $this->hasColorSupport();
        }

        parent::__construct($verbosity, $decorated, $formatter);
    }

    
    public function getStream()
    {
        return $this->stream;
    }

    
    protected function doWrite($message, $newline)
    {
        if (false === @fwrite($this->stream, $message.($newline ? PHP_EOL : ''))) {
                        throw new \RuntimeException('Unable to write output.');
        }

        fflush($this->stream);
    }

    
    protected function hasColorSupport()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
        }

        return function_exists('posix_isatty') && @posix_isatty($this->stream);
    }
}

}

	


namespace Symfony\Component\Console\Output{

use Symfony\Component\Console\Formatter\OutputFormatterInterface;


class ConsoleOutput extends StreamOutput implements ConsoleOutputInterface
{
    
    private $stderr;

    
    public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null)
    {
        $outputStream = $this->hasStdoutSupport() ? 'php://stdout' : 'php://output';
        $errorStream = $this->hasStderrSupport() ? 'php://stderr' : 'php://output';

        parent::__construct(fopen($outputStream, 'w'), $verbosity, $decorated, $formatter);

        $this->stderr = new StreamOutput(fopen($errorStream, 'w'), $verbosity, $decorated, $this->getFormatter());
    }

    
    public function setDecorated($decorated)
    {
        parent::setDecorated($decorated);
        $this->stderr->setDecorated($decorated);
    }

    
    public function setFormatter(OutputFormatterInterface $formatter)
    {
        parent::setFormatter($formatter);
        $this->stderr->setFormatter($formatter);
    }

    
    public function setVerbosity($level)
    {
        parent::setVerbosity($level);
        $this->stderr->setVerbosity($level);
    }

    
    public function getErrorOutput()
    {
        return $this->stderr;
    }

    
    public function setErrorOutput(OutputInterface $error)
    {
        $this->stderr = $error;
    }

    
    protected function hasStdoutSupport()
    {
        return false === $this->isRunningOS400();
    }

    
    protected function hasStderrSupport()
    {
        return false === $this->isRunningOS400();
    }

    
    private function isRunningOS400()
    {
        return 'OS400' === php_uname('s');
    }
}

}

	


namespace Symfony\Component\Console\Helper{

use Symfony\Component\Console\Output\OutputInterface;


class ProgressBar
{
        private $barWidth = 28;
    private $barChar;
    private $emptyBarChar = '-';
    private $progressChar = '>';
    private $format = null;
    private $redrawFreq = 1;

    
    private $output;
    private $step = 0;
    private $max;
    private $startTime;
    private $stepWidth;
    private $percent = 0.0;
    private $lastMessagesLength = 0;
    private $formatLineCount;
    private $messages;
    private $overwrite = true;

    private static $formatters;
    private static $formats;

    
    public function __construct(OutputInterface $output, $max = 0)
    {
        $this->output = $output;
        $this->setMaxSteps($max);

        if (!$this->output->isDecorated()) {
                        $this->overwrite = false;

            if ($this->max > 10) {
                                $this->setRedrawFrequency($max / 10);
            }
        }

        $this->setFormat($this->determineBestFormat());

        $this->startTime = time();
    }

    
    public static function setPlaceholderFormatterDefinition($name, $callable)
    {
        if (!self::$formatters) {
            self::$formatters = self::initPlaceholderFormatters();
        }

        self::$formatters[$name] = $callable;
    }

    
    public static function getPlaceholderFormatterDefinition($name)
    {
        if (!self::$formatters) {
            self::$formatters = self::initPlaceholderFormatters();
        }

        return isset(self::$formatters[$name]) ? self::$formatters[$name] : null;
    }

    
    public static function setFormatDefinition($name, $format)
    {
        if (!self::$formats) {
            self::$formats = self::initFormats();
        }

        self::$formats[$name] = $format;
    }

    
    public static function getFormatDefinition($name)
    {
        if (!self::$formats) {
            self::$formats = self::initFormats();
        }

        return isset(self::$formats[$name]) ? self::$formats[$name] : null;
    }

    public function setMessage($message, $name = 'message')
    {
        $this->messages[$name] = $message;
    }

    public function getMessage($name = 'message')
    {
        return $this->messages[$name];
    }

    
    public function getStartTime()
    {
        return $this->startTime;
    }

    
    public function getMaxSteps()
    {
        return $this->max;
    }

    
    public function getStep()
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.6 and will be removed in 3.0. Use the getProgress() method instead.', E_USER_DEPRECATED);

        return $this->getProgress();
    }

    
    public function getProgress()
    {
        return $this->step;
    }

    
    public function getStepWidth()
    {
        return $this->stepWidth;
    }

    
    public function getProgressPercent()
    {
        return $this->percent;
    }

    
    public function setBarWidth($size)
    {
        $this->barWidth = (int) $size;
    }

    
    public function getBarWidth()
    {
        return $this->barWidth;
    }

    
    public function setBarCharacter($char)
    {
        $this->barChar = $char;
    }

    
    public function getBarCharacter()
    {
        if (null === $this->barChar) {
            return $this->max ? '=' : $this->emptyBarChar;
        }

        return $this->barChar;
    }

    
    public function setEmptyBarCharacter($char)
    {
        $this->emptyBarChar = $char;
    }

    
    public function getEmptyBarCharacter()
    {
        return $this->emptyBarChar;
    }

    
    public function setProgressCharacter($char)
    {
        $this->progressChar = $char;
    }

    
    public function getProgressCharacter()
    {
        return $this->progressChar;
    }

    
    public function setFormat($format)
    {
                if (!$this->max && null !== self::getFormatDefinition($format.'_nomax')) {
            $this->format = self::getFormatDefinition($format.'_nomax');
        } elseif (null !== self::getFormatDefinition($format)) {
            $this->format = self::getFormatDefinition($format);
        } else {
            $this->format = $format;
        }

        $this->formatLineCount = substr_count($this->format, "\n");
    }

    
    public function setRedrawFrequency($freq)
    {
        $this->redrawFreq = (int) $freq;
    }

    
    public function start($max = null)
    {
        $this->startTime = time();
        $this->step = 0;
        $this->percent = 0.0;

        if (null !== $max) {
            $this->setMaxSteps($max);
        }

        $this->display();
    }

    
    public function advance($step = 1)
    {
        $this->setProgress($this->step + $step);
    }

    
    public function setCurrent($step)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.6 and will be removed in 3.0. Use the setProgress() method instead.', E_USER_DEPRECATED);

        $this->setProgress($step);
    }

    
    public function setOverwrite($overwrite)
    {
        $this->overwrite = (bool) $overwrite;
    }

    
    public function setProgress($step)
    {
        $step = (int) $step;
        if ($step < $this->step) {
            throw new \LogicException('You can\'t regress the progress bar.');
        }

        if ($this->max && $step > $this->max) {
            $this->max = $step;
        }

        $prevPeriod = (int) ($this->step / $this->redrawFreq);
        $currPeriod = (int) ($step / $this->redrawFreq);
        $this->step = $step;
        $this->percent = $this->max ? (float) $this->step / $this->max : 0;
        if ($prevPeriod !== $currPeriod || $this->max === $step) {
            $this->display();
        }
    }

    
    public function finish()
    {
        if (!$this->max) {
            $this->max = $this->step;
        }

        if ($this->step === $this->max && !$this->overwrite) {
                        return;
        }

        $this->setProgress($this->max);
    }

    
    public function display()
    {
        if (OutputInterface::VERBOSITY_QUIET === $this->output->getVerbosity()) {
            return;
        }

                $self = $this;
        $output = $this->output;
        $messages = $this->messages;
        $this->overwrite(preg_replace_callback("{%([a-z\-_]+)(?:\:([^%]+))?%}i", function ($matches) use ($self, $output, $messages) {
            if ($formatter = $self::getPlaceholderFormatterDefinition($matches[1])) {
                $text = call_user_func($formatter, $self, $output);
            } elseif (isset($messages[$matches[1]])) {
                $text = $messages[$matches[1]];
            } else {
                return $matches[0];
            }

            if (isset($matches[2])) {
                $text = sprintf('%'.$matches[2], $text);
            }

            return $text;
        }, $this->format));
    }

    
    public function clear()
    {
        if (!$this->overwrite) {
            return;
        }

        $this->overwrite(str_repeat("\n", $this->formatLineCount));
    }

    
    private function setMaxSteps($max)
    {
        $this->max = max(0, (int) $max);
        $this->stepWidth = $this->max ? Helper::strlen($this->max) : 4;
    }

    
    private function overwrite($message)
    {
        $lines = explode("\n", $message);

                if (null !== $this->lastMessagesLength) {
            foreach ($lines as $i => $line) {
                if ($this->lastMessagesLength > Helper::strlenWithoutDecoration($this->output->getFormatter(), $line)) {
                    $lines[$i] = str_pad($line, $this->lastMessagesLength, "\x20", STR_PAD_RIGHT);
                }
            }
        }

        if ($this->overwrite) {
                        $this->output->write("\x0D");
        } elseif ($this->step > 0) {
                        $this->output->writeln('');
        }

        if ($this->formatLineCount) {
            $this->output->write(sprintf("\033[%dA", $this->formatLineCount));
        }
        $this->output->write(implode("\n", $lines));

        $this->lastMessagesLength = 0;
        foreach ($lines as $line) {
            $len = Helper::strlenWithoutDecoration($this->output->getFormatter(), $line);
            if ($len > $this->lastMessagesLength) {
                $this->lastMessagesLength = $len;
            }
        }
    }

    private function determineBestFormat()
    {
        switch ($this->output->getVerbosity()) {
                        case OutputInterface::VERBOSITY_VERBOSE:
                return $this->max ? 'verbose' : 'verbose_nomax';
            case OutputInterface::VERBOSITY_VERY_VERBOSE:
                return $this->max ? 'very_verbose' : 'very_verbose_nomax';
            case OutputInterface::VERBOSITY_DEBUG:
                return $this->max ? 'debug' : 'debug_nomax';
            default:
                return $this->max ? 'normal' : 'normal_nomax';
        }
    }

    private static function initPlaceholderFormatters()
    {
        return array(
            'bar' => function (ProgressBar $bar, OutputInterface $output) {
                $completeBars = floor($bar->getMaxSteps() > 0 ? $bar->getProgressPercent() * $bar->getBarWidth() : $bar->getProgress() % $bar->getBarWidth());
                $display = str_repeat($bar->getBarCharacter(), $completeBars);
                if ($completeBars < $bar->getBarWidth()) {
                    $emptyBars = $bar->getBarWidth() - $completeBars - Helper::strlenWithoutDecoration($output->getFormatter(), $bar->getProgressCharacter());
                    $display .= $bar->getProgressCharacter().str_repeat($bar->getEmptyBarCharacter(), $emptyBars);
                }

                return $display;
            },
            'elapsed' => function (ProgressBar $bar) {
                return Helper::formatTime(time() - $bar->getStartTime());
            },
            'remaining' => function (ProgressBar $bar) {
                if (!$bar->getMaxSteps()) {
                    throw new \LogicException('Unable to display the remaining time if the maximum number of steps is not set.');
                }

                if (!$bar->getProgress()) {
                    $remaining = 0;
                } else {
                    $remaining = round((time() - $bar->getStartTime()) / $bar->getProgress() * ($bar->getMaxSteps() - $bar->getProgress()));
                }

                return Helper::formatTime($remaining);
            },
            'estimated' => function (ProgressBar $bar) {
                if (!$bar->getMaxSteps()) {
                    throw new \LogicException('Unable to display the estimated time if the maximum number of steps is not set.');
                }

                if (!$bar->getProgress()) {
                    $estimated = 0;
                } else {
                    $estimated = round((time() - $bar->getStartTime()) / $bar->getProgress() * $bar->getMaxSteps());
                }

                return Helper::formatTime($estimated);
            },
            'memory' => function (ProgressBar $bar) {
                return Helper::formatMemory(memory_get_usage(true));
            },
            'current' => function (ProgressBar $bar) {
                return str_pad($bar->getProgress(), $bar->getStepWidth(), ' ', STR_PAD_LEFT);
            },
            'max' => function (ProgressBar $bar) {
                return $bar->getMaxSteps();
            },
            'percent' => function (ProgressBar $bar) {
                return floor($bar->getProgressPercent() * 100);
            },
        );
    }

    private static function initFormats()
    {
        return array(
            'normal' => ' %current%/%max% [%bar%] %percent:3s%%',
            'normal_nomax' => ' %current% [%bar%]',

            'verbose' => ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%',
            'verbose_nomax' => ' %current% [%bar%] %elapsed:6s%',

            'very_verbose' => ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%',
            'very_verbose_nomax' => ' %current% [%bar%] %elapsed:6s%',

            'debug' => ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%',
            'debug_nomax' => ' %current% [%bar%] %elapsed:6s% %memory:6s%',
        );
    }
}

}



namespace {
	$concurrent = function_exists('pcntl_fork');
	if ($concurrent) {
		
define('PHP_INT_LENGTH', strlen(sprintf('%u', PHP_INT_MAX)));
function cofunc(callable $fn) {
	$pid = pcntl_fork();
	if (-1 == $pid) {
		trigger_error('could not fork', E_ERROR);
		return;
	}
	if ($pid) {
		return;
	}
	pcntl_signal(SIGCHLD, SIG_IGN);
	$params = [];
	if (func_num_args() > 1) {
		$params = array_slice(func_get_args(), 1);
	}
	call_user_func_array($fn, $params);
	die();
}

class CSP_Channel {
	const CLOSED = '-1';
	private $ipc;
	private $ipc_fn;
	private $key;
	private $closed = false;
	private $msg_count = 0;
	public function __construct() {
		$this->ipc_fn = tempnam(sys_get_temp_dir(), 'csp.' . uniqid('chn', true));
		$this->key = ftok($this->ipc_fn, 'A');
		$this->ipc = msg_get_queue($this->key, 0666);
		msg_set_queue($this->ipc, $cfg = [
			'msg_qbytes' => (1 * PHP_INT_LENGTH),
		]);

	}

	public function msg_count() {
		return $this->msg_count;
	}

	public function close() {
		$this->closed = true;
		do {
			$this->out();
			--$this->msg_count;
		} while ($this->msg_count >= 0);
		msg_remove_queue($this->ipc);
		file_exists($this->ipc_fn) && @unlink($this->ipc_fn);
	}

	public function in($msg) {
		if ($this->closed || !msg_queue_exists($this->key)) {
			return;
		}
		$shm = new Message();
		$shm->store($msg);
		$error = 0;
		@msg_send($this->ipc, 1, $shm->key(), false, true, $error);
		++$this->msg_count;
	}

	public function non_blocking_in($msg) {
		if ($this->closed || !msg_queue_exists($this->key)) {
			return self::CLOSED;
		}
		$shm = new Message();
		$shm->store($msg);
		$error = 0;
		@msg_send($this->ipc, 1, $shm->key(), false, false, $error);
		if (MSG_EAGAIN === $error) {
			$shmAbortedMessage = new Message($shm->key());
			$shmAbortedMessage->destroy();
			return false;
		}
		++$this->msg_count;
		$first_loop = true;
		do {
			$data = msg_stat_queue($this->ipc);
			if (!$first_loop && 0 == $data['msg_qnum']) {
				break;
			}
			$first_loop = false;
		} while (true);
		return true;
	}

	public function out() {
		if ($this->closed || !msg_queue_exists($this->key)) {
			return;
		}
		$msgtype = null;
		$ipcmsg = null;
		$error = null;
		msg_receive($this->ipc, 1, $msgtype, (1 * PHP_INT_LENGTH) + 1, $ipcmsg, false, 0, $error);
		--$this->msg_count;
		$shm = new Message($ipcmsg);
		$ret = $shm->fetch();
		return $ret;
	}

	public function non_blocking_out() {
		if ($this->closed || !msg_queue_exists($this->key)) {
			return [self::CLOSED, null];
		}
		$msgtype = null;
		$ipcmsg = null;
		$error = null;
		msg_receive($this->ipc, 1, $msgtype, (1 * PHP_INT_LENGTH) + 1, $ipcmsg, false, MSG_IPC_NOWAIT, $error);
		if (MSG_ENOMSG === $error) {
			return [false, null];
		}
		--$this->msg_count;
		$shm = new Message($ipcmsg);
		$ret = $shm->fetch();
		return [true, $ret];
	}
}
class Message {
	private $key;
	private $shm;
	public function __construct($key = null) {
		if (null === $key) {
			$key = ftok(tempnam(sys_get_temp_dir(), 'csp.' . uniqid('shm', true)), 'C');
		}
		$this->shm = shm_attach($key);
		if (false === $this->shm) {
			trigger_error('Unable to attach shared memory segment for channel', E_ERROR);
		}
		$this->key = $key;
	}

	public function store($msg) {
		shm_put_var($this->shm, 1, $msg);
		shm_detach($this->shm);
	}

	public function key() {
		return sprintf('%0' . PHP_INT_LENGTH . 'd', (int) $this->key);
	}

	public function fetch() {
		$ret = shm_get_var($this->shm, 1);
		$this->destroy();
		return $ret;

	}

	public function destroy() {
		if (shm_has_var($this->shm, 1)) {
			shm_remove_var($this->shm, 1);
		}
		shm_remove($this->shm);
	}
}

function make_channel() {
	return new CSP_Channel();
}


function select_channel(array $actions) {
	while (true) {
		foreach ($actions as $action) {
			if ('default' == $action[0]) {
				call_user_func_array($action[1]);
				break 2;
			} elseif (is_callable($action[1])) {
				$chn = &$action[0];
				$callback = &$action[1];

				list($ok, $result) = $chn->non_blocking_out();
				if (true === $ok) {
					call_user_func_array($callback, [$result]);
					break 2;
				}
			} elseif ($action[0] instanceof CSP_Channel) {
				$chn = &$action[0];
				$msg = &$action[1];
				$callback = &$action[2];
				$params = array_slice($action, 3);

				$ok = $chn->non_blocking_in($msg);
				if (CSP_Channel::CLOSED === $ok) {
					throw new Exception('Cannot send to closed channel');
				} elseif (true === $ok) {
					call_user_func($callback);
					break 2;
				}
			} else {
				throw new Exception('Invalid action for CSP select_channel');
			}
		}
	}
}

	}
	
interface Cacher {
	const DEFAULT_CACHE_FILENAME = '.php.tools.cache';

	public function create_db();

	public function is_changed($target, $filename);

	public function upsert($target, $filename, $content);
}

	$enableCache = false;
	if (class_exists('SQLite3')) {
		$enableCache = true;
		

final class Cache implements Cacher {
	private $db;

	private $noop = false;

	public function __construct($filename) {
		if (empty($filename)) {
			$this->noop = true;
			return;
		}

		$startDbCreation = false;
		if (is_dir($filename)) {
			$filename = realpath($filename) . DIRECTORY_SEPARATOR . self::DEFAULT_CACHE_FILENAME;
		}
		if (!file_exists($filename)) {
			$startDbCreation = true;
		}

		$this->setDb(new SQLite3($filename));
		$this->db->busyTimeout(1000);
		if ($startDbCreation) {
			$this->create_db();
		}
	}

	public function __destruct() {
		if ($this->noop) {
			return;
		}
		$this->db->close();
	}

	public function create_db() {
		if ($this->noop) {
			return;
		}
		$this->db->exec('CREATE TABLE cache (target TEXT, filename TEXT, hash TEXT, unique(target, filename));');
	}

	public function is_changed($target, $filename) {
		$content = file_get_contents($filename);
		if ($this->noop) {
			return $content;
		}
		$row = $this->db->querySingle('SELECT hash FROM cache WHERE target = "' . SQLite3::escapeString($target) . '" AND filename = "' . SQLite3::escapeString($filename) . '"', true);
		if (empty($row)) {
			return $content;
		}
		if ($this->calculateHash($content) != $row['hash']) {
			return $content;
		}
		return false;
	}

	public function upsert($target, $filename, $content) {
		if ($this->noop) {
			return;
		}
		$hash = $this->calculateHash($content);
		$this->db->exec('REPLACE INTO cache VALUES ("' . SQLite3::escapeString($target) . '","' . SQLite3::escapeString($filename) . '", "' . SQLite3::escapeString($hash) . '")');
	}

	private function calculateHash($content) {
		return sprintf('%u', crc32($content));
	}

	private function setDb($db) {
		$this->db = $db;
	}
}

	} else {
		

final class Cache implements Cacher {
	public function create_db() {}
	public function is_changed($target, $filename) {
		return file_get_contents($filename);
	}

	public function upsert($target, $filename, $content) {}
}

	}

	define('VERSION', '19.6.4');
	
function extractFromArgv($argv, $item) {
	return array_values(
		array_filter($argv,
			function ($v) use ($item) {
				return substr($v, 0, strlen('--' . $item)) !== '--' . $item;
			}
		)
	);
}

function extractFromArgvShort($argv, $item) {
	return array_values(
		array_filter($argv,
			function ($v) use ($item) {
				return substr($v, 0, strlen('-' . $item)) !== '-' . $item;
			}
		)
	);
}

function lint($file) {
	$output = null;
	$ret = null;
	exec('php -l ' . escapeshellarg($file), $output, $ret);
	return 0 === $ret;
}

function tabwriter(array $lines) {
	$colsize = [];
	foreach ($lines as $line) {
		foreach ($line as $idx => $text) {
			$cs = &$colsize[$idx];
			$len = strlen($text);
			$cs = max($cs, $len);
		}
	}

	$final = '';
	foreach ($lines as $line) {
		$out = '';
		foreach ($line as $idx => $text) {
			$cs = &$colsize[$idx];
			$out .= str_pad($text, $cs) . ' ';
		}
		$final .= rtrim($out) . PHP_EOL;
	}

	return $final;
}
	
function selfupdate($argv, $inPhar) {
	$opts = [
		'http' => [
			'method' => 'GET',
			'header' => "User-agent: phpfmt fmt.phar selfupdate\r\n",
		],
	];

	$context = stream_context_create($opts);

		$releases = json_decode(file_get_contents('https://api.github.com/repos/phpfmt/fmt/tags', false, $context), true);
	$commit = json_decode(file_get_contents($releases[0]['commit']['url'], false, $context), true);
	$files = json_decode(file_get_contents($commit['commit']['tree']['url'], false, $context), true);
	foreach ($files['tree'] as $file) {
		if ('fmt.phar' == $file['path']) {
			$phar_file = base64_decode(json_decode(file_get_contents($file['url'], false, $context), true)['content']);
		}
		if ('fmt.phar.sha1' == $file['path']) {
			$phar_sha1 = base64_decode(json_decode(file_get_contents($file['url'], false, $context), true)['content']);
		}
	}
	if (!isset($phar_sha1) || !isset($phar_file)) {
		fwrite(STDERR, 'Could not autoupdate - not release found' . PHP_EOL);
		exit(255);
	}
	if ($inPhar && !file_exists($argv[0])) {
		$argv[0] = dirname(Phar::running(false)) . DIRECTORY_SEPARATOR . $argv[0];
	}
	if (sha1_file($argv[0]) != $phar_sha1) {
		copy($argv[0], $argv[0] . '~');
		file_put_contents($argv[0], $phar_file);
		chmod($argv[0], 0777 & ~umask());
		fwrite(STDERR, 'Updated successfully' . PHP_EOL);
		exit(0);
	}
	fwrite(STDERR, 'Up-to-date!' . PHP_EOL);
	exit(0);
}


	
define('ST_AT', '@');
define('ST_BRACKET_CLOSE', ']');
define('ST_BRACKET_OPEN', '[');
define('ST_COLON', ':');
define('ST_COMMA', ',');
define('ST_CONCAT', '.');
define('ST_CURLY_CLOSE', '}');
define('ST_CURLY_OPEN', '{');
define('ST_DIVIDE', '/');
define('ST_DOLLAR', '$');
define('ST_EQUAL', '=');
define('ST_EXCLAMATION', '!');
define('ST_IS_GREATER', '>');
define('ST_IS_SMALLER', '<');
define('ST_MINUS', '-');
define('ST_MODULUS', '%');
define('ST_PARENTHESES_CLOSE', ')');
define('ST_PARENTHESES_OPEN', '(');
define('ST_PLUS', '+');
define('ST_QUESTION', '?');
define('ST_QUOTE', '"');
define('ST_REFERENCE', '&');
define('ST_SEMI_COLON', ';');
define('ST_TIMES', '*');
define('ST_BITWISE_OR', '|');
define('ST_BITWISE_XOR', '^');
if (!defined('T_POW')) {
	define('T_POW', '**');
}
if (!defined('T_POW_EQUAL')) {
	define('T_POW_EQUAL', '**=');
}
if (!defined('T_YIELD')) {
	define('T_YIELD', 'yield');
}
if (!defined('T_FINALLY')) {
	define('T_FINALLY', 'finally');
}
if (!defined('T_SPACESHIP')) {
	define('T_SPACESHIP', '<=>');
}
if (!defined('T_COALESCE')) {
	define('T_COALESCE', '??');
}

define('ST_PARENTHESES_BLOCK', 'ST_PARENTHESES_BLOCK');
define('ST_BRACKET_BLOCK', 'ST_BRACKET_BLOCK');
define('ST_CURLY_BLOCK', 'ST_CURLY_BLOCK');
	
abstract class FormatterPass {
	protected $cache = [];

	protected $code = '';

		protected $ignoreFutileTokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

	protected $indent = 0;

	protected $indentChar = "\t";

	protected $newLine = "\n";

		protected $ptr = 0;

	protected $tkns = [];

	protected $useCache = false;

	private $memo = [null, null];

	private $memoUseful = [null, null];

	abstract public function candidate($source, $foundTokens);

	abstract public function format($source);

	protected function alignPlaceholders($origPlaceholder, $contextCounter) {
		for ($j = 0; $j <= $contextCounter; ++$j) {
			$placeholder = sprintf($origPlaceholder, $j);
			if (false === strpos($this->code, $placeholder)) {
				continue;
			}
			if (1 === substr_count($this->code, $placeholder)) {
				$this->code = str_replace($placeholder, '', $this->code);
				continue;
			}
			$lines = explode($this->newLine, $this->code);
			$linesWithPlaceholder = [];
			$blockCount = 0;

			foreach ($lines as $idx => $line) {
				if (false !== strpos($line, $placeholder)) {
					$linesWithPlaceholder[$blockCount][] = $idx;
					continue;
				}
				++$blockCount;
				$linesWithPlaceholder[$blockCount] = [];
			}

			$i = 0;
			foreach ($linesWithPlaceholder as $group) {
				++$i;
				$farthest = 0;
				foreach ($group as $idx) {
					$farthest = max($farthest, strpos($lines[$idx], $placeholder));
				}
				foreach ($group as $idx) {
					$line = $lines[$idx];
					$current = strpos($line, $placeholder);
					$delta = abs($farthest - $current);
					if ($delta > 0) {
						$line = str_replace($placeholder, str_repeat(' ', $delta) . $placeholder, $line);
						$lines[$idx] = $line;
					}
				}
			}
			$this->code = str_replace($placeholder, '', implode($this->newLine, $lines));
		}
	}

	protected function appendCode($code = '') {
		$this->code .= $code;
	}

	protected function getCrlf() {
		return $this->newLine;
	}

	protected function getCrlfIndent() {
		return $this->getCrlf() . $this->getIndent();
	}

	protected function getIndent($increment = 0) {
		return str_repeat($this->indentChar, $this->indent + $increment);
	}

	protected function getSpace($true = true) {
		return $true ? ' ' : '';
	}

	protected function getToken($token) {
		$ret = [$token, $token];
		if (isset($token[1])) {
			$ret = $token;
		}
		return $ret;
	}

	protected function hasLn($text) {
		return (false !== strpos($text, $this->newLine));
	}

	protected function hasLnAfter() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspectToken();
		return T_WHITESPACE === $id && $this->hasLn($text);
	}

	protected function hasLnBefore() {
		$id = null;
		$text = null;
		list($id, $text) = $this->inspectToken(-1);
		return T_WHITESPACE === $id && $this->hasLn($text);
	}

	protected function hasLnLeftToken() {
		list(, $text) = $this->getToken($this->leftToken());
		return $this->hasLn($text);
	}

	protected function hasLnRightToken() {
		list(, $text) = $this->getToken($this->rightToken());
		return $this->hasLn($text);
	}

	protected function inspectToken($delta = 1) {
		if (!isset($this->tkns[$this->ptr + $delta])) {
			return [null, null];
		}
		return $this->getToken($this->tkns[$this->ptr + $delta]);
	}

	protected function isShortArray() {
		return !$this->leftTokenIs([
			ST_BRACKET_CLOSE,
			ST_CURLY_CLOSE,
			ST_PARENTHESES_CLOSE,
			ST_QUOTE,
			T_CONSTANT_ENCAPSED_STRING,
			T_STRING,
			T_VARIABLE,
		]);
	}

	protected function leftMemoTokenIs($token) {
		return $this->resolveFoundToken($this->memo[0], $token);
	}

	protected function leftMemoUsefulTokenIs($token, $debug = false) {
		return $this->resolveFoundToken($this->memoUseful[0], $token);
	}

	protected function leftToken($ignoreList = []) {
		$i = $this->leftTokenIdx($ignoreList);

		return $this->tkns[$i];
	}

	protected function leftTokenIdx($ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$i = $this->walkLeft($this->tkns, $this->ptr, $ignoreList);

		return $i;
	}

	protected function leftTokenIs($token, $ignoreList = []) {
		return $this->tokenIs('left', $token, $ignoreList);
	}

	protected function leftTokenSubsetAtIdx($tkns, $idx, $ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);
		$idx = $this->walkLeft($tkns, $idx, $ignoreList);

		return $idx;
	}

	protected function leftTokenSubsetIsAtIdx($tkns, $idx, $token, $ignoreList = []) {
		$idx = $this->leftTokenSubsetAtIdx($tkns, $idx, $ignoreList);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function leftUsefulToken() {
		return $this->leftToken($this->ignoreFutileTokens);
	}

	protected function leftUsefulTokenIdx() {
		return $this->leftTokenIdx($this->ignoreFutileTokens);
	}

	protected function leftUsefulTokenIs($token) {
		return $this->leftTokenIs($token, $this->ignoreFutileTokens);
	}

	protected function memoPtr() {
		$t = $this->tkns[$this->ptr][0];

		if (T_WHITESPACE !== $t) {
			$this->memo[0] = $this->memo[1];
			$this->memo[1] = $t;
		}

		if (T_WHITESPACE !== $t && T_COMMENT !== $t && T_DOC_COMMENT !== $t) {
			$this->memoUseful[0] = $this->memoUseful[1];
			$this->memoUseful[1] = $t;
		}
	}

	protected function peekAndCountUntilAny($tkns, $ptr, $tknids) {
		$tknids = array_flip($tknids);
		$tknsSize = sizeof($tkns);
		$countTokens = [];
		$id = null;
		for ($i = $ptr; $i < $tknsSize; ++$i) {
			$token = $tkns[$i];
			list($id) = $this->getToken($token);
			if (T_WHITESPACE == $id || T_COMMENT == $id || T_DOC_COMMENT == $id) {
				continue;
			}
			if (!isset($countTokens[$id])) {
				$countTokens[$id] = 0;
			}
			++$countTokens[$id];
			if (isset($tknids[$id])) {
				break;
			}
		}
		return [$id, $countTokens];
	}

	protected function printAndStopAt($tknids) {
		if (is_scalar($tknids)) {
			$tknids = [$tknids];
		}
		$tknids = array_flip($tknids);
		$touchedLn = false;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			if (!$touchedLn && T_WHITESPACE == $id && $this->hasLn($text)) {
				$touchedLn = true;
			}
			if (isset($tknids[$id])) {
				return [$id, $text, $touchedLn];
			}
			$this->appendCode($text);
		}
	}

	protected function printAndStopAtEndOfParamBlock() {
		$count = 1;
		$paramCount = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];

			if (ST_COMMA == $id && 1 == $count) {
				++$paramCount;
			}
			if (ST_BRACKET_OPEN == $id) {
				$this->appendCode($text);
				$this->printBlock(ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
				continue;
			}
			if (ST_CURLY_OPEN == $id || T_CURLY_OPEN == $id || T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				$this->appendCode($text);
				$this->printCurlyBlock();
				continue;
			}
			if (ST_PARENTHESES_OPEN == $id) {
				++$count;
			}
			if (ST_PARENTHESES_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				prev($this->tkns);
				break;
			}
			$this->appendCode($text);
		}
		return $paramCount;
	}

	protected function printBlock($start, $end) {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);

			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function printCurlyBlock() {
		$count = 1;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);

			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function printUntil($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($tknid == $id) {
				break;
			}
		}
	}

	protected function printUntilAny($tknids) {
		$tknids = array_flip($tknids);
		$whitespaceNewLine = false;
		$id = null;
		if (isset($tknids[$this->newLine])) {
			$whitespaceNewLine = true;
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			$this->cache = [];
			$this->appendCode($text);
			if ($whitespaceNewLine && T_WHITESPACE == $id && $this->hasLn($text)) {
				break;
			}
			if (isset($tknids[$id])) {
				break;
			}
		}
		return $id;
	}

	protected function printUntilTheEndOfString() {
		$this->printUntil(ST_QUOTE);
	}

	protected function refInsert(&$tkns, &$ptr, $item) {
		array_splice($tkns, $ptr, 0, [$item]);
		++$ptr;
	}

	protected function refSkipBlocks($tkns, &$ptr) {
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];

			if (T_CLOSE_TAG == $id) {
				return;
			}

			if (T_DO == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				continue;
			}

			if (T_WHILE == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				if ($this->rightTokenSubsetIsAtIdx(
					$tkns,
					$ptr,
					ST_CURLY_OPEN,
					$this->ignoreFutileTokens
				)) {
					$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
					$this->refWalkCurlyBlock($tkns, $ptr);
					return;
				}
			}

			if (T_FOR == $id || T_FOREACH == $id || T_SWITCH == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				return;
			}

			if (T_TRY == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				while (
					$this->rightTokenSubsetIsAtIdx(
						$tkns,
						$ptr,
						T_CATCH,
						$this->ignoreFutileTokens
					)
				) {
					$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
					$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
					$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
					$this->refWalkCurlyBlock($tkns, $ptr);
				}
				if ($this->rightTokenSubsetIsAtIdx(
					$tkns,
					$ptr,
					T_FINALLY,
					$this->ignoreFutileTokens
				)) {
					$this->refWalkUsefulUntil($tkns, $ptr, T_FINALLY);
					$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
					$this->refWalkCurlyBlock($tkns, $ptr);
				}
				return;
			}

			if (T_IF == $id) {
				$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
				$this->refWalkCurlyBlock($tkns, $ptr);
				while (true) {
					if (
						$this->rightTokenSubsetIsAtIdx(
							$tkns,
							$ptr,
							T_ELSEIF,
							$this->ignoreFutileTokens
						)
					) {
						$this->refWalkUsefulUntil($tkns, $ptr, ST_PARENTHESES_OPEN);
						$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
						$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
						$this->refWalkCurlyBlock($tkns, $ptr);
						continue;
					} elseif (
						$this->rightTokenSubsetIsAtIdx(
							$tkns,
							$ptr,
							T_ELSE,
							$this->ignoreFutileTokens
						)
					) {
						$this->refWalkUsefulUntil($tkns, $ptr, ST_CURLY_OPEN);
						$this->refWalkCurlyBlock($tkns, $ptr);
						break;
					}
					break;
				}
				return;
			}

			if (
				ST_CURLY_OPEN == $id ||
				T_CURLY_OPEN == $id ||
				T_DOLLAR_OPEN_CURLY_BRACES == $id
			) {
				$this->refWalkCurlyBlock($tkns, $ptr);
				continue;
			}

			if (ST_PARENTHESES_OPEN == $id) {
				$this->refWalkBlock($tkns, $ptr, ST_PARENTHESES_OPEN, ST_PARENTHESES_CLOSE);
				continue;
			}

			if (ST_BRACKET_OPEN == $id) {
				$this->refWalkBlock($tkns, $ptr, ST_BRACKET_OPEN, ST_BRACKET_CLOSE);
				continue;
			}

			if (ST_SEMI_COLON == $id) {
				return;
			}
		}
		--$ptr;
	}

	protected function refSkipIfTokenIsAny($tkns, &$ptr, $skipIds) {
		$skipIds = array_flip($skipIds);
		++$ptr;
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];
			if (!isset($skipIds[$id])) {
				break;
			}
		}
	}

	protected function refWalkBackUsefulUntil($tkns, &$ptr, array $expectedId) {
		$expectedId = array_flip($expectedId);
		do {
			$ptr = $this->walkLeft($tkns, $ptr, $this->ignoreFutileTokens);
		} while (isset($expectedId[$tkns[$ptr][0]]));
	}

	protected function refWalkBlock($tkns, &$ptr, $start, $end) {
		$count = 0;
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];
			if ($start == $id) {
				++$count;
			}
			if ($end == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function refWalkBlockReverse($tkns, &$ptr, $start, $end) {
		$count = 0;
		for (; $ptr >= 0; --$ptr) {
			$id = $tkns[$ptr][0];
			if ($start == $id) {
				--$count;
			}
			if ($end == $id) {
				++$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function refWalkCurlyBlock($tkns, &$ptr) {
		$count = 0;
		for ($sizeOfTkns = sizeof($tkns); $ptr < $sizeOfTkns; ++$ptr) {
			$id = $tkns[$ptr][0];
			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function refWalkCurlyBlockReverse($tkns, &$ptr) {
		$count = 0;
		for (; $ptr >= 0; --$ptr) {
			$id = $tkns[$ptr][0];
			if (ST_CURLY_OPEN == $id) {
				--$count;
			}
			if (T_CURLY_OPEN == $id) {
				--$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				--$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				++$count;
			}
			if (0 == $count) {
				break;
			}
		}
	}

	protected function refWalkUsefulUntil($tkns, &$ptr, $expectedId) {
		do {
			$ptr = $this->walkRight($tkns, $ptr, $this->ignoreFutileTokens);
		} while ($expectedId != $tkns[$ptr][0]);
	}

	protected function refWalkUsefulUntilReverse($tkns, &$ptr, $expectedId) {
		do {
			$ptr = $this->walkLeft($tkns, $ptr, $this->ignoreFutileTokens);
		} while ($ptr >= 0 && $expectedId != $tkns[$ptr][0]);
	}

	protected function render($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}

		$tkns = array_filter($tkns);
		$str = '';
		foreach ($tkns as $token) {
			list(, $text) = $this->getToken($token);
			$str .= $text;
		}
		return $str;
	}

	protected function renderLight($tkns = null) {
		if (null == $tkns) {
			$tkns = $this->tkns;
		}
		$str = '';
		foreach ($tkns as $token) {
			$str .= $token[1];
		}
		return $str;
	}

	protected function rightToken($ignoreList = []) {
		$i = $this->rightTokenIdx($ignoreList);

		return $this->tkns[$i];
	}

	protected function rightTokenIdx($ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);

		$i = $this->walkRight($this->tkns, $this->ptr, $ignoreList);

		return $i;
	}

	protected function rightTokenIs($token, $ignoreList = []) {
		return $this->tokenIs('right', $token, $ignoreList);
	}

	protected function rightTokenSubsetAtIdx($tkns, $idx, $ignoreList = []) {
		$ignoreList = $this->resolveIgnoreList($ignoreList);
		$idx = $this->walkRight($tkns, $idx, $ignoreList);

		return $idx;
	}

	protected function rightTokenSubsetIsAtIdx($tkns, $idx, $token, $ignoreList = []) {
		$idx = $this->rightTokenSubsetAtIdx($tkns, $idx, $ignoreList);

		return $this->resolveTokenMatch($tkns, $idx, $token);
	}

	protected function rightUsefulToken() {
		return $this->rightToken($this->ignoreFutileTokens);
	}

	protected function rightUsefulTokenIdx() {
		return $this->rightTokenIdx($this->ignoreFutileTokens);
	}

	protected function rightUsefulTokenIs($token) {
		return $this->rightTokenIs($token, $this->ignoreFutileTokens);
	}

	protected function rtrimAndAppendCode($code = '') {
		$this->code = rtrim($this->code) . $code;
	}

	protected function rtrimLnAndAppendCode($code = '') {
		$this->code = rtrim($this->code, "\t ") . $code;
	}

	protected function scanAndReplace(&$tkns, &$ptr, $start, $end, $call, $lookFor) {
		$lookFor = array_flip($lookFor);
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = '';
		$tknCount = 1;
		$foundPotentialTokens = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if (isset($lookFor[$id])) {
				$foundPotentialTokens = true;
			}
			if ($start == $id) {
				++$tknCount;
			}
			if ($end == $id) {
				--$tknCount;
			}
			$tkns[$ptr] = null;
			if (0 == $tknCount) {
				break;
			}
			$tmp .= $text;
		}
		if ($foundPotentialTokens) {
			return $start . str_replace($placeholder, '', $this->{$call}($placeholder . $tmp)) . $end;
		}
		return $start . $tmp . $end;
	}

	protected function scanAndReplaceCurly(&$tkns, &$ptr, $start, $call, $lookFor) {
		$lookFor = array_flip($lookFor);
		$placeholder = '<?php' . ' /*\x2 PHPOPEN \x3*/';
		$tmp = '';
		$tknCount = 1;
		$foundPotentialTokens = false;
		while (list($ptr, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if (isset($lookFor[$id])) {
				$foundPotentialTokens = true;
			}
			if (ST_CURLY_OPEN == $id) {
				if (empty($start)) {
					$start = ST_CURLY_OPEN;
				}
				++$tknCount;
			}
			if (T_CURLY_OPEN == $id) {
				if (empty($start)) {
					$start = ST_CURLY_OPEN;
				}
				++$tknCount;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				if (empty($start)) {
					$start = ST_DOLLAR . ST_CURLY_OPEN;
				}
				++$tknCount;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$tknCount;
			}
			$tkns[$ptr] = null;
			if (0 == $tknCount) {
				break;
			}
			$tmp .= $text;
		}
		if ($foundPotentialTokens) {
			return $start . str_replace($placeholder, '', $this->{$call}($placeholder . $tmp)) . ST_CURLY_CLOSE;
		}
		return $start . $tmp . ST_CURLY_CLOSE;
	}

	protected function setIndent($increment) {
		$this->indent += $increment;
		if ($this->indent < 0) {
			$this->indent = 0;
		}
	}

	protected function siblings($tkns, $ptr) {
		$ignoreList = $this->resolveIgnoreList([T_WHITESPACE]);
		$left = $this->walkLeft($tkns, $ptr, $ignoreList);
		$right = $this->walkRight($tkns, $ptr, $ignoreList);
		return [$left, $right];
	}

	protected function substrCountTrailing($haystack, $needle) {
		return strlen(rtrim($haystack, " \t")) - strlen(rtrim($haystack, " \t" . $needle));
	}

	protected function tokenIs($direction, $token, $ignoreList = []) {
		if ('left' != $direction) {
			$direction = 'right';
		}
		if (!$this->useCache) {
			return $this->{$direction . 'tokenSubsetIsAtIdx'}($this->tkns, $this->ptr, $token, $ignoreList);
		}

		$key = $this->calculateCacheKey($direction, $ignoreList);
		if (isset($this->cache[$key])) {
			return $this->resolveTokenMatch($this->tkns, $this->cache[$key], $token);
		}

		$this->cache[$key] = $this->{$direction . 'tokenSubsetAtIdx'}($this->tkns, $this->ptr, $ignoreList);

		return $this->resolveTokenMatch($this->tkns, $this->cache[$key], $token);
	}

	protected function walkAndAccumulateCurlyBlock(&$tkns) {
		$count = 1;
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$ret .= $text;

			if (ST_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_CURLY_OPEN == $id) {
				++$count;
			}
			if (T_DOLLAR_OPEN_CURLY_BRACES == $id) {
				++$count;
			}
			if (ST_CURLY_CLOSE == $id) {
				--$count;
			}
			if (0 == $count) {
				break;
			}
		}
		return $ret;
	}

	protected function walkAndAccumulateStopAt(&$tkns, $tknid) {
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			if ($tknid == $id) {
				prev($tkns);
				break;
			}
			$ret .= $text;
		}
		return $ret;
	}

	protected function walkAndAccumulateStopAtAny(&$tkns, $tknids) {
		$tknids = array_flip($tknids);
		$ret = '';
		$id = null;
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if (isset($tknids[$id])) {
				prev($tkns);
				break;
			}
			$ret .= $text;
		}
		return [$ret, $id];
	}

	protected function walkAndAccumulateUntil(&$tkns, $tknid) {
		$ret = '';
		while (list($index, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$ret .= $text;
			if ($tknid == $id) {
				break;
			}
		}
		return $ret;
	}

	protected function walkAndAccumulateUntilAny(&$tkns, $tknids) {
		$tknids = array_flip($tknids);
		$ret = '';
		while (list(, $token) = each($tkns)) {
			list($id, $text) = $this->getToken($token);
			$ret .= $text;
			if (isset($tknids[$id])) {
				break;
			}
		}
		return [$ret, $id];
	}

	protected function walkUntil($tknid) {
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->getToken($token);
			$this->ptr = $index;
			if ($id == $tknid) {
				return [$id, $text];
			}
		}
	}

	protected function walkUsefulRightUntil($tkns, $idx, $tokens) {
		$ignoreList = $this->resolveIgnoreList($this->ignoreFutileTokens);
		$tokens = array_flip($tokens);

		while ($idx > 0 && isset($tkns[$idx])) {
			$idx = $this->walkRight($tkns, $idx, $ignoreList);
			if (isset($tokens[$tkns[$idx][0]])) {
				return $idx;
			}
		}

		return;
	}

	private function calculateCacheKey($direction, $ignoreList) {
		return $direction . "\x2" . implode('', $ignoreList);
	}

	private function resolveFoundToken($foundToken, $token) {
		if ($foundToken === $token) {
			return true;
		} elseif (is_array($token) && isset($foundToken[1]) && in_array($foundToken[0], $token)) {
			return true;
		} elseif (is_array($token) && !isset($foundToken[1]) && in_array($foundToken, $token)) {
			return true;
		} elseif (isset($foundToken[1]) && $foundToken[0] == $token) {
			return true;
		}

		return false;
	}

	private function resolveIgnoreList($ignoreList = []) {
		if (!empty($ignoreList)) {
			return array_flip($ignoreList);
		}
		return [T_WHITESPACE => true];
	}

	private function resolveTokenMatch($tkns, $idx, $token) {
		if (!isset($tkns[$idx])) {
			return false;
		}

		$foundToken = $tkns[$idx];
		return $this->resolveFoundToken($foundToken, $token);
	}

	private function walkLeft($tkns, $idx, $ignoreList) {
		$i = $idx;
		while (--$i >= 0 && isset($ignoreList[$tkns[$i][0]]));
		return $i;
	}

	private function walkRight($tkns, $idx, $ignoreList) {
		$i = $idx;
		$tknsSize = sizeof($tkns) - 1;
		while (++$i < $tknsSize && isset($ignoreList[$tkns[$i][0]]));
		return $i;
	}
}

	
abstract class AdditionalPass extends FormatterPass {
	abstract public function getDescription();

	abstract public function getExample();
}

	

abstract class BaseCodeFormatter {
	protected $passes = [
		'StripSpaces' => false,

		'ReplaceBooleanAndOr' => false,
		'EliminateDuplicatedEmptyLines' => false,

		'RTrim' => false,
		'WordWrap' => false,

		'ConvertOpenTagWithEcho' => false,
		'RestoreComments' => false,
		'UpgradeToPreg' => false,
		'DocBlockToComment' => false,
		'LongArray' => false,

		'StripExtraCommaInArray' => false,
		'NoSpaceAfterPHPDocBlocks' => false,
		'RemoveUseLeadingSlash' => false,
		'ShortArray' => false,
		'MergeElseIf' => false,
		'SplitElseIf' => false,
		'AutoPreincrement' => false,
		'MildAutoPreincrement' => false,

		'CakePHPStyle' => false,

		'StripNewlineAfterClassOpen' => false,
		'StripNewlineAfterCurlyOpen' => false,

		'SortUseNameSpace' => false,
		'SpaceAroundExclamationMark' => false,

		'TightConcat' => false,

		'PSR2IndentWithSpace' => false,
		'AlignPHPCode' => false,
		'AllmanStyleBraces' => false,
		'NamespaceMergeWithOpenTag' => false,
		'MergeNamespaceWithOpenTag' => false,

		'LeftAlignComment' => false,

		'PSR2AlignObjOp' => false,
		'PSR2EmptyFunction' => false,
		'PSR2SingleEmptyLineAndStripClosingTag' => false,
		'PSR2ModifierVisibilityStaticOrder' => false,
		'PSR2CurlyOpenNextLine' => false,
		'PSR2LnAfterNamespace' => false,
		'PSR2KeywordsLowerCase' => false,

		'PSR1MethodNames' => false,
		'PSR1ClassNames' => false,

		'PSR1ClassConstants' => false,
		'PSR1BOMMark' => false,

		'EliminateDuplicatedEmptyLines' => false,
		'IndentTernaryConditions' => false,
		'ReindentComments' => false,
		'ReindentEqual' => false,
		'Reindent' => false,
		'ReindentAndAlignObjOps' => false,
		'ReindentObjOps' => false,

		'AlignDoubleSlashComments' => false,
		'AlignTypehint' => false,
		'AlignGroupDoubleArrow' => false,
		'AlignDoubleArrow' => false,
		'AlignEquals' => false,
		'AlignConstVisibilityEquals' => false,

		'ReindentSwitchBlocks' => false,
		'ReindentColonBlocks' => false,

		'SplitCurlyCloseAndTokens' => false,
		'ResizeSpaces' => false,

		'StripSpaceWithinControlStructures' => false,

		'StripExtraCommaInList' => false,
		'YodaComparisons' => false,

		'MergeDoubleArrowAndArray' => false,
		'MergeCurlyCloseAndDoWhile' => false,
		'MergeParenCloseWithCurlyOpen' => false,
		'NormalizeLnAndLtrimLines' => false,
		'ExtraCommaInArray' => false,
		'SmartLnAfterCurlyOpen' => false,
		'AddMissingCurlyBraces' => false,
		'OnlyOrderUseClauses' => false,
		'OrderAndRemoveUseClauses' => false,
		'AutoImportPass' => false,
		'ConstructorPass' => false,
		'SettersAndGettersPass' => false,
		'NormalizeIsNotEquals' => false,
		'RemoveIncludeParentheses' => false,
		'TwoCommandsInSameLine' => false,

		'SpaceBetweenMethods' => false,
		'GeneratePHPDoc' => false,
		'ReturnNull' => false,
		'AddMissingParentheses' => false,
		'WrongConstructorName' => false,
		'JoinToImplode' => false,
		'EncapsulateNamespaces' => false,
		'PrettyPrintDocBlocks' => false,
		'StrictBehavior' => false,
		'StrictComparison' => false,
		'ReplaceIsNull' => false,
		'DoubleToSingleQuote' => false,
		'LeftWordWrap' => false,
		'ClassToSelf' => false,
		'ClassToStatic' => false,
		'PSR2MultilineFunctionParams' => false,
		'SpaceAroundControlStructures' => false,

		'OrderMethodAndVisibility' => false,
		'OrderMethod' => false,
		'OrganizeClass' => false,
		'AutoSemicolon' => false,
		'PSR1OpenTags' => false,
		'PHPDocTypesToFunctionTypehint' => false,
		'RemoveSemicolonAfterCurly' => false,
		'NewLineBeforeReturn' => false,
		'EchoToPrint' => false,
		'TrimSpaceBeforeSemicolon' => false,
		'StripNewlineWithinClassBody' => false,
	];

	private $hasAfterExecutedPass = false;

	private $hasAfterFormat = false;

	private $hasBeforeFormat = false;

	private $hasBeforePass = false;

	private $shortcircuit = [
		'AlignDoubleArrow' => ['AlignGroupDoubleArrow'],
		'AlignGroupDoubleArrow' => ['AlignDoubleArrow'],
		'AllmanStyleBraces' => ['PSR2CurlyOpenNextLine'],
		'OnlyOrderUseClauses' => ['OrderAndRemoveUseClauses'],
		'OrderAndRemoveUseClauses' => ['OnlyOrderUseClauses'],
		'OrganizeClass' => ['ReindentComments', 'RestoreComments'],
		'ReindentAndAlignObjOps' => ['ReindentObjOps'],
		'ReindentComments' => ['OrganizeClass', 'RestoreComments'],
		'ReindentObjOps' => ['ReindentAndAlignObjOps'],
		'RestoreComments' => ['OrganizeClass', 'ReindentComments'],

		'PSR1OpenTags' => ['ReindentComments'],
		'PSR1BOMMark' => ['ReindentComments'],
		'PSR1ClassConstants' => ['ReindentComments'],
		'PSR1ClassNames' => ['ReindentComments'],
		'PSR1MethodNames' => ['ReindentComments'],
		'PSR2KeywordsLowerCase' => ['ReindentComments'],
		'PSR2IndentWithSpace' => ['ReindentComments'],
		'PSR2LnAfterNamespace' => ['ReindentComments'],
		'PSR2CurlyOpenNextLine' => ['ReindentComments'],
		'PSR2ModifierVisibilityStaticOrder' => ['ReindentComments'],
		'PSR2SingleEmptyLineAndStripClosingTag' => ['ReindentComments'],
	];

	private $shortcircuits = [];

	public function __construct() {
		$this->passes['AddMissingCurlyBraces'] = new AddMissingCurlyBraces();
		$this->passes['EliminateDuplicatedEmptyLines'] = new EliminateDuplicatedEmptyLines();
		$this->passes['ExtraCommaInArray'] = new ExtraCommaInArray();
		$this->passes['LeftAlignComment'] = new LeftAlignComment();
		$this->passes['MergeCurlyCloseAndDoWhile'] = new MergeCurlyCloseAndDoWhile();
		$this->passes['MergeDoubleArrowAndArray'] = new MergeDoubleArrowAndArray();
		$this->passes['MergeParenCloseWithCurlyOpen'] = new MergeParenCloseWithCurlyOpen();
		$this->passes['NormalizeIsNotEquals'] = new NormalizeIsNotEquals();
		$this->passes['NormalizeLnAndLtrimLines'] = new NormalizeLnAndLtrimLines();
		$this->passes['OrderAndRemoveUseClauses'] = new OrderAndRemoveUseClauses();
		$this->passes['Reindent'] = new Reindent();
		$this->passes['ReindentColonBlocks'] = new ReindentColonBlocks();
		$this->passes['ReindentComments'] = new ReindentComments();
		$this->passes['ReindentEqual'] = new ReindentEqual();
		$this->passes['ReindentObjOps'] = new ReindentObjOps();
		$this->passes['RemoveIncludeParentheses'] = new RemoveIncludeParentheses();
		$this->passes['ResizeSpaces'] = new ResizeSpaces();
		$this->passes['RTrim'] = new RTrim();
		$this->passes['SplitCurlyCloseAndTokens'] = new SplitCurlyCloseAndTokens();
		$this->passes['StripExtraCommaInList'] = new StripExtraCommaInList();
		$this->passes['TwoCommandsInSameLine'] = new TwoCommandsInSameLine();

		$this->hasAfterExecutedPass = method_exists($this, 'afterExecutedPass');
		$this->hasAfterFormat = method_exists($this, 'afterFormat');
		$this->hasBeforePass = method_exists($this, 'beforePass');
		$this->hasBeforeFormat = method_exists($this, 'beforeFormat');
	}

	public function disablePass($pass) {
		$this->passes[$pass] = null;
	}

	public function enablePass($pass) {
		$args = func_get_args();
		if (!isset($args[1])) {
			$args[1] = null;
		}

		if (!class_exists($pass)) {
			$passName = sprintf('ExternalPass%s', $pass);
			$passes = array_reverse($this->passes, true);
			$passes[$passName] = new ExternalPass($pass);
			$this->passes = array_reverse($passes, true);
			return;
		}

		if (isset($this->shortcircuits[$pass])) {
			return;
		}

		$this->passes[$pass] = new $pass($args[1]);

		$scPasses = &$this->shortcircuit[$pass];
		if (isset($scPasses)) {
			foreach ($scPasses as $scPass) {
				$this->disablePass($scPass);
				$this->shortcircuits[$scPass] = $pass;
			}
		}
	}

	public function forcePass($pass) {
		$this->shortcircuits = [];
		$args = func_get_args();
		return call_user_func_array([$this, 'enablePass'], $args);
	}

	public function formatCode($source = '') {
		$passes = array_map(
			function ($pass) {
				return clone $pass;
			},
			array_filter($this->passes)
		);
		list($foundTokens, $commentStack) = $this->getFoundTokens($source);
		$this->hasBeforeFormat && $this->beforeFormat($source);
		while (($pass = array_pop($passes))) {
			$this->hasBeforePass && $this->beforePass($source, $pass);
			if ($pass->candidate($source, $foundTokens)) {
				if (isset($pass->commentStack)) {
					$pass->commentStack = $commentStack;
				}
				$source = $pass->format($source);
				$this->hasAfterExecutedPass && $this->afterExecutedPass($source, $pass);
			}
		}
		$this->hasAfterFormat && $this->afterFormat($source);
		return $source;
	}

	public function getPassesNames() {
		return array_keys(array_filter($this->passes));
	}

	protected function getToken($token) {
		$ret = [$token, $token];
		if (isset($token[1])) {
			$ret = $token;
		}
		return $ret;
	}

	private function getFoundTokens($source) {
		$foundTokens = [];
		$commentStack = [];
		$tkns = token_get_all($source);
		foreach ($tkns as $token) {
			list($id, $text) = $this->getToken($token);
			$foundTokens[$id] = $id;
			if (T_COMMENT === $id) {
				$commentStack[] = [$id, $text];
			}
		}
		return [$foundTokens, $commentStack];
	}
}


	
class SandboxedPass extends FormatterPass {
	public function candidate($source, $foundTokens) {
		return static::candidate($source, $foundTokens);
	}

	public function format($source) {
		return static::format($source);
	}

	final protected function alignPlaceholders($origPlaceholder, $contextCounter) {
		return parent::alignPlaceholders($origPlaceholder, $contextCounter);
	}

	final protected function appendCode($code = '') {
		return parent::appendCode($code);
	}

	final protected function getCrlf() {
		return parent::getCrlf();
	}

	final protected function getCrlfIndent() {
		return parent::getCrlfIndent();
	}

	final protected function getIndent($increment = 0) {
		return parent::getIndent($increment);
	}

	final protected function getSpace($true = true) {
		return parent::getSpace($true);
	}

	final protected function getToken($token) {
		return parent::getToken($token);
	}

	final protected function hasLn($text) {
		return parent::hasLn($text);
	}

	final protected function hasLnAfter() {
		return parent::hasLnAfter();
	}

	final protected function hasLnBefore() {
		return parent::hasLnBefore();
	}

	final protected function hasLnLeftToken() {
		return parent::hasLnLeftToken();
	}

	final protected function hasLnRightToken() {
		return parent::hasLnRightToken();
	}

	final protected function inspectToken($delta = 1) {
		return parent::inspectToken($delta);
	}

	final protected function isShortArray() {
		return parent::isShortArray();
	}

	final protected function leftMemoTokenIs($token) {
		return parent::leftMemoTokenIs($token);
	}

	final protected function leftMemoUsefulTokenIs($token, $debug = false) {
		return parent::leftMemoUsefulTokenIs($token, $debug);
	}

	final protected function leftToken($ignoreList = []) {
		return parent::leftToken($ignoreList = []);
	}

	final protected function leftTokenIdx($ignoreList = []) {
		return parent::leftTokenIdx($ignoreList = []);
	}

	final protected function leftTokenIs($token, $ignoreList = []) {
		return parent::leftTokenIs($token, $ignoreList = []);
	}

	final protected function leftTokenSubsetAtIdx($tkns, $idx, $ignoreList = []) {
		return parent::leftTokenSubsetAtIdx($tkns, $idx, $ignoreList = []);
	}

	final protected function leftTokenSubsetIsAtIdx($tkns, $idx, $token, $ignoreList = []) {
		return parent::leftTokenSubsetIsAtIdx($tkns, $idx, $token, $ignoreList = []);
	}

	final protected function leftUsefulToken() {
		return parent::leftUsefulToken();
	}

	final protected function leftUsefulTokenIdx() {
		return parent::leftUsefulTokenIdx();
	}

	final protected function leftUsefulTokenIs($token) {
		return parent::leftUsefulTokenIs($token);
	}

	final protected function memoPtr() {
		return parent::memoPtr();
	}

	final protected function peekAndCountUntilAny($tkns, $ptr, $tknids) {
		return parent::peekAndCountUntilAny($tkns, $ptr, $tknids);
	}

	final protected function printAndStopAt($tknids) {
		return parent::printAndStopAt($tknids);
	}

	final protected function printAndStopAtEndOfParamBlock() {
		return parent::printAndStopAtEndOfParamBlock();
	}

	final protected function printBlock($start, $end) {
		return parent::printBlock($start, $end);
	}

	final protected function printCurlyBlock() {
		return parent::printCurlyBlock();
	}

	final protected function printUntil($tknid) {
		return parent::printUntil($tknid);
	}

	final protected function printUntilAny($tknids) {
		return parent::printUntilAny($tknids);
	}

	final protected function printUntilTheEndOfString() {
		return parent::printUntilTheEndOfString();
	}

	final protected function refInsert(&$tkns, &$ptr, $item) {
		return parent::refInsert($tkns, $ptr, $item);
	}

	final protected function refSkipBlocks($tkns, &$ptr) {
		return parent::refSkipBlocks($tkns, $ptr);
	}

	final protected function refSkipIfTokenIsAny($tkns, &$ptr, $skipIds) {
		return parent::refSkipIfTokenIsAny($tkns, $ptr, $skipIds);
	}

	final protected function refWalkBackUsefulUntil($tkns, &$ptr, array $expectedId) {
		return parent::refWalkBackUsefulUntil($tkns, $ptr, $expectedId);
	}

	final protected function refWalkBlock($tkns, &$ptr, $start, $end) {
		return parent::refWalkBlock($tkns, $ptr, $start, $end);
	}

	final protected function refWalkBlockReverse($tkns, &$ptr, $start, $end) {
		return parent::refWalkBlockReverse($tkns, $ptr, $start, $end);
	}

	final protected function refWalkCurlyBlock($tkns, &$ptr) {
		return parent::refWalkCurlyBlock($tkns, $ptr);
	}

	final protected function refWalkCurlyBlockReverse($tkns, &$ptr) {
		return parent::refWalkCurlyBlockReverse($tkns, $ptr);
	}

	final protected function refWalkUsefulUntil($tkns, &$ptr, $expectedId) {
		return parent::refWalkUsefulUntil($tkns, $ptr, $expectedId);
	}

	final protected function render($tkns = null) {
		return parent::render($tkns);
	}

	final protected function renderLight($tkns = null) {
		return parent::renderLight($tkns);
	}

	final protected function rightToken($ignoreList = []) {
		return parent::rightToken($ignoreList);
	}

	final protected function rightTokenIdx($ignoreList = []) {
		return parent::rightTokenIdx($ignoreList);
	}

	final protected function rightTokenIs($token, $ignoreList = []) {
		return parent::rightTokenIs($token, $ignoreList);
	}

	final protected function rightTokenSubsetAtIdx($tkns, $idx, $ignoreList = []) {
		return parent::rightTokenSubsetAtIdx($tkns, $idx, $ignoreList);
	}

	final protected function rightTokenSubsetIsAtIdx($tkns, $idx, $token, $ignoreList = []) {
		return parent::rightTokenSubsetIsAtIdx($tkns, $idx, $token, $ignoreList);
	}

	final protected function rightUsefulToken() {
		return parent::rightUsefulToken();
	}

	final protected function rightUsefulTokenIdx() {
		return parent::rightUsefulTokenIdx();
	}

	final protected function rightUsefulTokenIs($token) {
		return parent::rightUsefulTokenIs($token);
	}

	final protected function rtrimAndAppendCode($code = '') {
		return parent::rtrimAndAppendCode($code);
	}

	final protected function rtrimLnAndAppendCode($code = '') {
		return parent::rtrimLnAndAppendCode($code);
	}

	final protected function scanAndReplace(&$tkns, &$ptr, $start, $end, $call, $lookFor) {
		return parent::scanAndReplace($tkns, $ptr, $start, $end, $call, $lookFor);
	}

	final protected function scanAndReplaceCurly(&$tkns, &$ptr, $start, $call, $lookFor) {
		return parent::scanAndReplaceCurly($tkns, $ptr, $start, $call, $lookFor);
	}

	final protected function setIndent($increment) {
		return parent::setIndent($increment);
	}

	final protected function siblings($tkns, $ptr) {
		return parent::siblings($tkns, $ptr);
	}

	final protected function substrCountTrailing($haystack, $needle) {
		return parent::substrCountTrailing($haystack, $needle);
	}

	final protected function tokenIs($direction, $token, $ignoreList = []) {
		return parent::tokenIs($direction, $token, $ignoreList);
	}

	final protected function walkAndAccumulateCurlyBlock(&$tkns) {
		return parent::walkAndAccumulateCurlyBlock($tkns);
	}

	final protected function walkAndAccumulateStopAt(&$tkns, $tknid) {
		return parent::walkAndAccumulateStopAt($tkns, $tknid);
	}

	final protected function walkAndAccumulateStopAtAny(&$tkns, $tknids) {
		return parent::walkAndAccumulateStopAtAny($tkns, $tknids);
	}

	final protected function walkAndAccumulateUntil(&$tkns, $tknid) {
		return parent::walkAndAccumulateUntil($tkns, $tknid);
	}

	final protected function walkAndAccumulateUntilAny(&$tkns, $tknids) {
		return parent::walkAndAccumulateUntilAny($tkns, $tknids);
	}

	final protected function walkUntil($tknid) {
		return parent::walkUntil($tknid);
	}
}
	

final class CodeFormatter extends BaseCodeFormatter {
	public function __construct($passName) {
		if (get_parent_class($passName) != 'SandboxedPass') {
			throw new Exception($passName . ' is not a sandboxed pass (SandboxedPass)');
		}

		$this->passes = ['ExternalPass' => new $passName()];
	}

	public function disablePass($pass) {}
	public function enablePass($pass) {}
}


	if (!isset($inPhar)) {
		$inPhar = false;
	}
	if (!isset($testEnv)) {
		
function showHelp($argv, $enableCache, $inPhar) {
	echo 'Usage: ' . $argv[0] . ' [-h] --pass=Pass ', PHP_EOL;

	$options = [];
	if ($inPhar) {
		$options['--version'] = 'version';
	}

	ksort($options);
	$maxLen = max(array_map(function ($v) {
		return strlen($v);
	}, array_keys($options)));
	foreach ($options as $k => $v) {
		echo '  ', str_pad($k, $maxLen), '  ', $v, PHP_EOL;
	}

	echo PHP_EOL, 'It reads input from stdin, and outputs content on stdout.', PHP_EOL;
	echo PHP_EOL, 'It will derive "Pass" into a file in local directory appended with ".php" ("Pass.php"). Make sure it inherits from SandboxedPass.', PHP_EOL;
}

$getoptLongOptions = ['help', 'pass::'];
if ($inPhar) {
	$getoptLongOptions[] = 'version';
}
$opts = getopt('h', $getoptLongOptions);

if (isset($opts['version'])) {
	if ($inPhar) {
		echo $argv[0], ' ', VERSION, PHP_EOL;
	}
	exit(0);
}

if (!isset($opts['pass'])) {
	fwrite(STDERR, 'pass is not declared. cannot run.');
	exit(1);
}

$pass = sprintf('%s.php', basename($opts['pass']));
if (!file_get_contents($pass)) {
	fwrite(STDERR, sprintf('pass file "%s" is not found. cannot run.', $pass));
	exit(1);
}
include $pass;

if (isset($opts['h']) || isset($opts['help'])) {
	showHelp($argv, $enableCache, $inPhar);
	exit(0);
}

$fmt = new CodeFormatter(basename($opts['pass']));
echo $fmt->formatCode(file_get_contents('php://stdin'));
exit(0);

	}
}