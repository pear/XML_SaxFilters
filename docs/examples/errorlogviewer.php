#!/usr/local/bin/php -q
<?php
/**
* Shows simple usage of SaxFilters to parse an XML error log
*/
# require_once 'XML/SaxFilters.php'; // This is the normal way to do it

# Done to help development
if ( !@include_once 'XML/SaxFilters.php' ) {
    define('XML_SAXFILTERS', '../../');
    include_once XML_SAXFILTERS . 'SaxFilters.php';
}

if ($argc < 2 || in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
?>
    Usage:      <?php echo $argv[0]; ?> <log_filename>
                    [-l=<level>]
                    [-s=<error_script>]
                    [-m=<message>]
    
    <log_filename>: name of the log file to parse
    <level>: PHP error level to filter for e.g. E_USER_WARNING
    <error_script>: name of PHP script where error occurred
    <message>: string to compare with error messages
<?
    die();
}

if (version_compare(phpversion(), '4.3.0', '<') ||
    php_sapi_name() == 'cgi') {
    define('STDOUT', fopen('php://stdout', 'w'));
    define('STDERR', fopen('php://stderr', 'w'));
    register_shutdown_function(
        create_function('', 'fclose(STDOUT); fclose(STDERR); return true;'));
}

//---------------------------------------------------------------------
// Define a customer handler class - just displays stuff
class LogFilter extends XML_SaxFilters_AbstractFilter {
    var $filename;   
    var $inError = FALSE;
    var $displayError = TRUE;
    var $buffer = '';
    
    function LogFilter($filename,$level=NULL,$errorscript=NULL,$message=NULL) {
        $this->filename = $filename;

        if ( $errorscript ) $this->errorscript = $errorscript;
        if ( $message ) $this->message = $message;
    }

    function startDoc() {
        fwrite(STDOUT,"Parsing {$this->filename}\n\n");
    }
    
    function open(& $tag,& $attribs) {
        if ( $this->inError ) {
            $this->child->open($tag,$attribs);
        } else if ( strtolower($tag) == 'error' ) {
            $this->inError = TRUE;
            $this->displayError = TRUE;
            $this->buffer = '';
        }
    }

    function close(& $tag) {
        if ( strtolower($tag) == 'error' ) {
            $this->inError = FALSE;
            if ( $this->displayError ) {
                fwrite(STDOUT,$this->buffer."\n");
            }
        }
        if ( $this->inError ) {
            $this->child->close($tag);
        }
    }
    function data(& $data) {
        if ( $this->inError ) {
            $this->displayError = $this->displayError & $this->child->data($data);
            $data = trim($data);
            if ( !empty($data) ) {
                $this->buffer.= $data . ' ';
            }
        }
    }
    function endDoc() {
        fwrite(STDOUT,"\nParsing complete\n");
    }
}
//---------------------------------------------------------------------
class ErrorLevelFilter extends XML_SaxFilters_AbstractFilter {
    var $level = NULL;
    var $inLevel = FALSE;
    function ErrorLevelFilter($level = NULL) {
        $levels = array (
            'E_ERROR',
            'E_WARNING',
            'E_NOTICE',        
            'E_USER_ERROR',
            'E_USER_WARNING',
            'E_USER_NOTICE',
        );
        if ( in_array($level,$levels) ) $this->level = $level;   
    }
    function open(& $tag,& $attribs) {
        if ( strtolower($tag) == 'level' ) {
            $this->inLevel = TRUE;
        } else {
            $this->child->open($tag,$attribs);
        }
    }
    function close(& $tag) {
        if ( strtolower($tag) == 'level' ) {
            $this->inLevel = FALSE;
        } else {
            $this->child->close($tag);
        }
    }
    function data(& $data) {
        if ( $this->inLevel ) {  
            if ( $this->level ) {
                if ( $this->level == $data ) {
                    return TRUE;
                } else {
                    return FALSE;
                }
            } else {
                return TRUE;
            }
        } else {
            return $this->child->data($data);
        }
    }
}
//---------------------------------------------------------------------
class ErrorScriptFilter extends XML_SaxFilters_AbstractFilter {
    var $errorscript = NULL;
    var $inFile = FALSE;
    function ErrorScriptFilter($errorscript = NULL) {
        if ( $errorscript ) $this->errorscript = $errorscript;
    }
    function open(& $tag,& $attribs) {
        if ( strtolower($tag) == 'file' ) {
            $this->inFile = TRUE;
        } else {
            $this->child->open($tag,$attribs);
        }
    }
    function close(& $tag) {
        if ( strtolower($tag) == 'file' ) {
            $this->inFile = FALSE;
        } else {
            $this->child->close($tag);
        }
    }
    function data(& $data) {
        if ( $this->inFile ) {
            if ( $this->errorscript ) {
                if ( !strpos ( $data, $this->errorscript ) === FALSE ) {
                    return TRUE;
                } else {
                    return FALSE;
                }
            } else {
                return TRUE;
            }
        } else {                    
            return $this->child->data($data);
        }
    }
}
//---------------------------------------------------------------------
class MessageFilter extends XML_SaxFilters_AbstractFilter {
    var $message = NULL;
    var $inMessage = FALSE;
    function ErrorScriptFilter($message = NULL) {
        if ( $message ) $this->message = $message;
    }
    function open(& $tag,& $attribs) {
        if ( strtolower($tag) == 'message' ) {
            $this->inMessage = TRUE;
        } else {
            $this->child->open($tag,$attribs);
        }
    }
    function close(& $tag) {
        if ( strtolower($tag) == 'message' ) {
            $this->inMessage = FALSE;
        } else {
            $this->child->close($tag);
        }
    }
    function data(& $data) {
        if ( $this->inMessage ) {
            if ( $this->message ) {
                if ( FALSE !== strpos ( $data, $this->message ) ) {
                    return TRUE;
                } else {
                    return FALSE;
                }
            } else {
                return TRUE;
            }
        } else {
            return $this->child->data($data);
        }
    }
}
//---------------------------------------------------------------------
//---------------------------------------------------------------------
class PassThruFilter extends XML_SaxFilters_AbstractFilter {
    function data(& $data) {
        return TRUE;
    }
}
//---------------------------------------------------------------------
// Create the parser
$logfile = $argv[1];
$parser = & XML_SaxFilters_createParser('Expat','File',$logfile);

$opts = array('l'=>NULL,'s'=>NULL,'m'=>NULL);
$args = array_slice($argv,2);
foreach ( $args as $arg ) {
    if ( strpos($arg,'-') === 0 ) {
        $arg = substr($arg,1);
    }
    $arg = explode('=',$arg);
    if ( array_key_exists($arg[0],$opts) ) {
        $opts[$arg[0]] = $arg[1];
    }
}

$filters = array();
$filters[]= & new LogFilter($logfile);
if ( isset($opts['l']) ) {
    $filters[]= & new ErrorLevelFilter($opts['l']);
}
if ( isset($opts['s']) ) {
    $filters[]= & new ErrorScriptFilter($opts['s']);
}
if ( isset($opts['m']) ) {
    $filters[]= & new MessageFilter($opts['m']);
}
$filters[]= & new PassThruFilter();

XML_SaxFilters_buildChain($parser,$filters);

// Parse
if ( ! $parser->parse() ) {
    $error = $parser->getError();
    fwrite (STDERR,$error->getMessage());
}
?>