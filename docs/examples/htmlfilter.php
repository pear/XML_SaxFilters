<?php
/*
Filtering HTML output to remove whitespace and
convert tags / attrs to lower. Note how using
references allows the incoming XML stream to be
modified.
*/
# require_once 'XML/SaxFilters.php'; // This is the normal way to do it

# Done to help development
if ( !@include_once 'XML/SaxFilters.php' ) {
    define('XML_SAXFILTERS', '../../');
    include_once XML_SAXFILTERS . 'SaxFilters.php';
}

//----------------------------------------------------------------------------
/**
* Strips whitespace
*/
class WhitespaceFilter extends XML_SaxFilters_AbstractFilter {
    /**
    * Whether we're inside an HTML page
    * @var boolean (default = FALSE)
    * @access private
    */
    var $inHtml = FALSE;

    /**
    * Whether we're inside an HTML where the contents
    * are preformatted e.g. pre or script
    * @var boolean (default = FALSE)
    * @access private
    */
    var $inPre = FALSE;
    
    function open(& $tag,& $attrs,$empty) {
        $this->child->open($tag,$attrs,$empty);
        switch ( strtolower($tag) ) {
            case 'textarea':
            case 'script':
            case 'pre':
                $this->inPre = TRUE;
            break;
            case 'html':
                $this->inHtml = TRUE;
            break;
        }
    }

    function close(& $tag,& $empty) {
        $this->child->close($tag,$empty);
        switch ( strtolower($tag) ) {
            case 'textarea':
            case 'script':
            case 'pre':
                $this->inPre = FALSE;
            break;
            case 'html':
                $this->inHtml = FALSE;
            break;
        }
    }
    function data(& $text) {
        $this->child->data($text);
        if ( !$this->inPre && $this->inHtml ) {
            // Note here - DOT NOT pass by reference
            $text = preg_replace('/\s+/u', ' ', $text);
        }
    }
}

//----------------------------------------------------------------------------
/**
* Converts tags and attribute names to lower case
*/
class TagsToLowerFilter extends XML_SaxFilters_AbstractFilter {

    function open(& $tag,& $attrs,$empty) {
        $tag = strtolower($tag);
        if ( is_array($attrs) ) {
            $attrs = array_change_key_case($attrs,CASE_LOWER);
        }
    }

    function close(& $tag,& $empty) {
        $tag = strtolower($tag);
    }
}

//----------------------------------------------------------------------------
/**
* Reconstructs the HTML - this filter is chained directly to the parser. By
* chaining the WhitespaceFilter and TagsToLowerFilter filter to this filters,
* it's possible to modify the incoming XML stream before it is handled by
* HTMLFilter
*/
class HTMLFilter extends XML_SaxFilters_AbstractFilter {
    var $html = '';
    function open(& $tag,& $attrs,$empty) {
        $this->child->open($tag,$attrs,$empty);
        $this->html.= '<'.$tag;
        if ( is_array($attrs) ) {
            foreach ( $attrs as $key => $val ) {
                if ( $val === TRUE ) {
                    $this->html.=', '.$key;
                } else {
                    $this->html.=' '.$key.='="'.$val.'"';
                }
            }
        }
        if ( $empty ) {
            $this->html.= '/>';
        } else {
            $this->html.= '>';
        }
    }

    function close(& $tag,$empty) {
        $this->child->close($tag,$empty);
        if ( !$empty ) {
            $this->html.='</'.$tag.'>';
        }
    }
    
    function data(& $data) {
        $this->child->data($data);
        $this->html.= $data;
    }
    
    function escape(& $data) {
        $this->child->escape($data);
        $this->html.='<!'.$data.'>';
    }
    function getHTML() {
        return $this->html;
    }
}
//----------------------------------------------------------------------------

// Capture the HTML with output buffering
ob_start();
include 'example.html';
$html = ob_get_contents();
ob_end_clean();

// Create the filters
$HF = & new HTMLFilter();
$filters = array();
$filters[] = & $HF;
$filters[] = & new WhitespaceFilter();
$filters[] = & new TagsToLowerFilter();

// Use the HTMLSax parser - note fourth argument - builds the chain
$parser = & XML_SaxFilters_createParser('HTMLSax','String',$html);

// Chain the filters
XML_SaxFilters_buildChain($parser,$filters);

// Parse the HTML
$parser->parse();

// Display it
echo $HF->getHTML();
?>