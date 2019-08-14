<?php
/**
 * This file is part of Neteven_NetevenSync for Magento.
 *
 * @license All rights reserved
 * @author Jacques Bodin-Hullin <j.bodinhullin@monsieurbiz.com> <@jacquesbh>
 * @category Neteven
 * @package Neteven_NetevenSync
 * @copyright Copyright (c) 2015 Neteven (http://www.neteven.com/)
 */

/**
 * Logger Helper
 * @package Neteven_NetevenSync
 */
class Neteven_NetevenSync_Helper_Logger
{

    /**
     * Log filename
     * @const string
     */
    const LOG_FILENAME = 'netevensync_execution-traces.log';

    /**
     * Is init?
     * <p>If the logger is not initialized, it won't log.</p>
     * @var bool
     */
    protected $_isInit = false;

    /**
     * Depth of the next line
     * @var int
     */
    protected $_depth = 0;

    /**
     * Current trace
     * @var string
     */
    protected $_trace = "";

    /**
     * Not logged traces
     * @var array
     */
    protected $_traces = array();

    /**
     * Tab characters
     * @var string
     */
    protected $_tab = "    ";

    /**
     * Timer of the current execution
     * <p>Using microtime(true);</p>
     * @var float
     */
    protected $_timer = null;

    /**
     * Left comparison array
     * @var array
     */
    protected $_leftComparison = null;

    /**
     * Label of the comparison
     * @var string
     */
    protected $_labelComparison = null;

    /**
     * Transaction trace
     * <p>NULL if no transaction running.</p>
     * @var string|null
     */
    protected $_transactionTrace = null;

    /**
     * Depth of the next line in transaction
     * @var int
     */
    protected $_transactionDepth = null;

    /**
     * Init a log trace
     * @param string $title Trace's title
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function init($title)
    {
        $this->_cleanup();
        $this->_isInit = true;
        $version = Mage::helper('netevensync')->getVersion();
        $this->_addLine("[v$version] " . strtoupper($title), '#');
        return $this;
    }

    /**
     * Start new part in the current trace
     * @param string $title Part's title
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function part($title)
    {
        $this->_setDepth(0);
        $this->_addLine($title . ":");
        $this->up();
        return $this;
    }

    /**
     * Add tabulation for the next elements
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function up()
    {
        if ($this->_transactionTrace !== null) {
            $this->_transactionDepth++;
        } else {
            $this->_depth++;
        }
        return $this;
    }

    /**
     * Remove tabulation for the next elements
     * <p>Can't be negative</p>
     * @param int $depth How many levels to down
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function down($depth = 1)
    {
        if ($this->_transactionTrace !== null) {
            $this->_transactionDepth -= $depth;
            if ($this->_transactionDepth < 0) {
                $this->_transactionDepth = 0;
            }
        } else {
            $this->_depth -= $depth;
            if ($this->_depth < 0) {
                $this->_depth = 0;
            }
        }
        return $this;
    }

    /**
     * Add step in the current trace
     * @param string $label Step's label
     * @param mixed $message Step's message. Default to empty string.
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function step($label, $message = "")
    {
        // Check message
        if (is_bool($message)) {
            $message = $message ? "yes" : "no";
        } elseif (!is_string($message)) {
            $data = $message;
            $message = "";
        }

        // If question mark is present, remove the colon
        if (strpos($label, "?") !== false) {
            $format = "%s %s";
        } else {
            $format = "%s: %s";
        }

        $this->_addLine(sprintf($format, $label, $message));

        if (isset($data)) {
            $this
                ->up()
                ->data($data)
                ->down()
            ;
        }

        return $this;
    }

    /**
     * Add data in the current trace
     * @param array|string $data Label of the $value, or array of data
     * @param mixed $value Value (with $data as string)
     * @param int $recursive Number of recursive iterations
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function data($data, $value = null, $recursive = 1)
    {
        if ($data instanceof Varien_Object) {
            $data = $data->getData();
        }

        if (is_array($data)) {
            if (!empty($data)) {
                foreach ($data as $label => $message) {
                    $this->data($label, $message);
                }
            } else {
                $this->_addLine("_empty array_");
            }
        } else {
            $this->_addLine(sprintf("`%s` = `%s`", $data, $this->_getVarDump($value)));
            if ($recursive > 0) {
                $this->up();
                if ($value instanceof Varien_Object) {
                    $this->data($value->getData(), null, --$recursive);
                } elseif (is_array($value)) {
                    $this->data($value, null, --$recursive);
                }
                $this->down();
            }
        }
        return $this;
    }

    /**
     * Add store information in the current trace
     * @param Mage_Core_Model_Store $store The store to log
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function logStore(Mage_Core_Model_Store $store)
    {
        $this->step("**[STORE] {$store->getName()} store view**", array(
            "id"                   => $store->getId(),
            "code"                 => $store->getCode(),
            "name"                 => $store->getName(),
            "base_currency"        => $store->getBaseCurrencyCode(),
            "default_currency"     => $store->getDefaultCurrencyCode(),
            "current_currency"     => $store->getCurrentCurrencyCode(),
            "available_currencies" => $store->getAvailableCurrencyCodes(),
        ));
        return $this;
    }

    /**
     * Add information in the current trace
     * @param string $info Info message
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function info($info)
    {
        $this->_addLine(sprintf("[i] *%s*", $info));
        return $this;
    }

    /**
     * Add error in the current trace
     * @param string $error Error message
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function err($error)
    {
        $this->_addLine(sprintf("[!!!] *%s*", $error));
        return $this;
    }

    /**
     * Add condition to trace and return it
     * @param string $label
     * @param mixed $condition
     * @return $condition
     */
    public function condition($label, $condition)
    {
        $this
            ->step($label)
            ->up()
            ->result($condition)
            ->down()
        ;
        return $condition;
    }

    /**
     * Add an exception message to the trace
     * @param Exception $e
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function exception(Exception $e)
    {
        $this->_addLine(sprintf("**[!!! Exception] %s**", $e->getMessage()));
        return $this;
    }

    /**
     * Start an array comparison
     * @param string $label Label of the comparison
     * @param array $left Left element to compare
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function startComparison($label, array $left)
    {
        $this->_labelComparison = $label;
        $this->_leftComparison = $left;
        return $this;
    }

    /**
     * End an array comparison
     * @param array $right Right element to compare
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function endComparison(array $right)
    {
        $comparison = array();

        // Compare left to right
        foreach ($this->_leftComparison as $key => $leftValue) {
            $rightValue = !isset($right[$key]) ? null : $right[$key];
            if ($leftValue !== $rightValue) {
                $comparison[$key] = array(
                    'left' => $leftValue,
                    'right' => $rightValue
                );
            }
        }

        // Compare right to left
        foreach ($right as $key => $rightValue) {
            if (isset($comparison[$key])) {
                continue;
            }
            $leftValue = !isset($this->_leftComparison[$key]) ? null : $this->_leftComparison[$key];
            if ($leftValue !== $rightValue) {
                $comparison[$key] = array(
                    'left' => $leftValue,
                    'right' => $rightValue
                );
            }
        }

        // Display
        $this
            ->step(sprintf("Comparison - %s", $this->_labelComparison))
            ->up()
        ;
        foreach ($comparison as $key => $element) {
            $this->_addLine(sprintf(
                "`%s`: `%s` => `%s`",
                $key,
                $this->_getVarDump($element['left']),
                $this->_getVarDump($element['right'])
            ));
        }

        // Reset
        $this->_leftComparison  = null;
        $this->_labelComparison = null;

        return $this;
    }

    /**
     * Add result to the current trace
     * @param mixed $result The result to log
     * @param null|string $label Label of the result if you want
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function result($result, $label = null)
    {
        if (is_string($label)) {
            $this->_addLine(sprintf("=> **%s** (%s)", $this->_getVarDump($result), $label));
        } else {
            $this->_addLine(sprintf("=> **%s**", $this->_getVarDump($result)));
        }
        return $this;
    }

    /**
     * End the current trace
     * <p>We just add the word END… technicaly the trace is still opened</p>
     * @param bool $log Log the trace at the end. Default to TRUE
     * @param bool $reset Reset after logging. Default to FALSE. Otherwise we cleanup.
     * @param null|int $level Log level
     * @param string Log filename
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function end($log = true, $reset = true, $level = null, $file = self::LOG_FILENAME)
    {
        $this->_setDepth(0);
        $this->_addLine("END");
        if ($log) {
            $this->log($reset, $level, $file);
        }
        return $this;
    }

    /**
     * Reset the current trace
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function reset()
    {
        $this->_trace            = "";
        $this->_depth            = 0;
        $this->_timer            = null;
        $this->_isInit           = false;
        $this->_transactionDepth = null;
        $this->_transactionTrace = null;

        return $this;
    }

    /**
     * Retrieve the current trace
     * @param bool $cleanup TRUE if you want to cleanup at the same time. Default to FALSE.
     * @return string The current trace
     */
    public function getTrace($cleanup = false)
    {
        $trace = $this->_trace;
        if ($cleanup) {
            $this->_cleanup();
        }
        return $trace;
    }

    /**
     * Retrieve all the traces (which are stored in the Logger)
     * @param bool $cleanup TRUE if you want to cleanup at the same time. Default to FALSE.
     * @return array
     */
    public function getTraces($cleanup = false)
    {
        if ($cleanup) {
            $this->_cleanup();
        }
        return $this->_traces;
    }

    /**
     * Log the current trace
     * @param bool $reset Reset after logging. Default to TRUE. Otherwise we cleanup.
     * @param null|int $level Log level
     * @param string Log filename
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function log($reset = true, $level = null, $file = self::LOG_FILENAME)
    {
        if (Mage::getStoreConfigFlag('netevensync/general/debug_advanced')) {
            if (strlen($this->_trace)) {
                if (strpos(strtolower($file), 'neteven') === false) {
                    // Add neteven because we use this to filter the log files in config
                    $file = sprintf("neteven_%s", $file);
                }
                Mage::log("\n" . $this->_trace, $level, $file, true);
            }
        }
        if ($reset) {
            $this->reset();
        } else {
            $this->_cleanup();
        }
        return $this;
    }

    /**
     * Start transaction
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function transaction()
    {
        $this->_transactionTrace = "";
        $this->_transactionDepth = $this->_depth;
        return $this;
    }

    /**
     * Commit transaction
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function commit()
    {
        $this->_trace .= $this->_transactionTrace;
        $this->_depth = $this->_transactionDepth;
        return $this->rollback();
    }

    /**
     * Rollback transaction
     * @return Neteven_NetevenSync_Helper_Logger
     */
    public function rollback()
    {
        $this->_transactionTrace = null;
        $this->_transactionDepth = null;
        return $this;
    }

    /**
     * Add a new line in the trace
     * @param string $line The line to add
     * @param bool|string $bullet The bullet to use.
     * <p>If TRUE: default bullet ("*")
     * If FALSE: no bullet.
     * If string: use it as bullet.</p>
     */
    protected function _addLine($line, $bullet = true)
    {
        // Not initialized?
        if (!$this->_isInit) {
            return;
        }

        $newLine = "";
        if ($this->_transactionTrace !== null) {
            if ($this->_transactionDepth > 0) {
                $newLine .= str_repeat($this->_tab, $this->_transactionDepth);
            }
        } else {
            if ($this->_depth > 0) {
                $newLine .= str_repeat($this->_tab, $this->_depth);
            }
        }
        if ($bullet === true) {
            $newLine .= "* ";
        } elseif (is_string($bullet)) {
            $newLine .= $bullet . " ";
        }
        $newLine .= $line;

        // Display memory and datetime/timer.
        /*
        $nbSpaces = 75 - strlen($newLine);
        if ($nbSpaces > 0) {
            $newLine .= str_repeat(" ", $nbSpaces);
        }
        $newLine .= sprintf(" `%s - %s`", $this->_getMemory(), $this->_getTimerAsString());
        //*/

        if ($this->_transactionTrace !== null) {
            $this->_transactionTrace .= $newLine . "\n";
        } else {
            $this->_trace .= $newLine . "\n";
        }
    }

    /**
     * Set the depth
     * @param int $depth
     * @return Neteven_NetevenSync_Helper_Logger
     */
    protected function _setDepth($depth)
    {
        if ($this->_transactionTrace !== null) {
            $this->_transactionDepth = $depth;
        } else {
            $this->_depth = $depth;
        }
        return $this;
    }

    /**
     * Clean up the current trace
     * <p>Keep the current trace in memory if it exists.</p>
     */
    protected function _cleanup()
    {
        if (!empty($this->_trace)) {
            $this->_traces[] = $this->_trace;
        }
        $this->reset();
    }

    /**
     * Retrieve the current memory usage readable by human
     * @return string
     */
    protected function _getMemory()
    {
        $size = memory_get_usage(true);
        $unit = array('b','Kb','Mb','Gb','Tb','Pb');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 6) . ' ' . $unit[$i];
    }

    /**
     * Retrieve the timer
     * <p>If it's the first time it returns the datetime</p>
     * @return string
     */
    protected function _getTimerAsString()
    {
        if (is_null($this->_timer)) {
            $this->_timer = microtime(true);
            return date('c');
        }
        $time = microtime(true);
        $diff = $time - $this->_timer;
        return (string) '+' . round($diff, 4) . 's';
    }

    /**
     * Retrieve a "var_dump" of the variable
     * @param mixed $var
     * @return string
     */
    protected function _getVarDump($var)
    {
        if (is_object($var)) {
            return sprintf("object(%s)", get_class($var));
        } elseif (is_array($var)) {
            return sprintf("array(count=%d)", count($var));
        }

        ob_start();
        var_dump($var);
        $dump = trim(ob_get_contents());
        ob_end_clean();

        return $dump;
    }

    /**
     * Magic method: To string
     * @return string
     */
    public function __toString()
    {
        return $this->_trace;
    }

    /**
     * Destructor
     * <p>Don't loose a trace :)</p>
     */
    public function __destruct()
    {
        $this->_cleanup();
        foreach ($this->_traces as $trace) {
            $this->_trace = "[!!!] LOGGER DESTRUCTION\n" . $trace;
            $this->log();
        }
    }

}
