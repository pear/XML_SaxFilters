<?php
/**
* Shows two filters chained together
*/
# require_once 'XML/SaxFilters.php'; // This is the normal way to do it

# Done to help development
if ( !@include_once 'XML/SaxFilters.php' ) {
    define('XML_SAXFILTERS', '../../');
    include_once XML_SAXFILTERS . 'SaxFilters.php';
}

//---------------------------------------------------------------------
/**
* Make a seperate class for both filters to write output to
*/
class Output {
    var $output = '';
    var $indent = '';
    function writeLine($text) {
        $this->output.=$this->indent.$text."\n";
    }
    function fetch() {
        return $this->output;
    }
    function addIndent() {
        $this->indent.="\t";
    }
    function removeIndent() {
        $this->indent = substr_replace($this->indent,'',0,1);
    }
}

//---------------------------------------------------------------------
// Filters for Language tag
class LanguageFilter extends XML_SaxFilters_AbstractFilter
/* implements XML_SaxFilters_FilterInterface */
{
    /**
    * Instance of Output
    */
    var $Output;
    
    var $inLanguage = FALSE;
    
    function LanguageFilter(& $Output) {
        $this->Output = & $Output;
    }
   
    // Opening tag handler
    function open(& $tag,& $attribs) {
        // Call child filter
        $this->child->open($tag,$attribs);
        
        if ( $tag == 'language' ) {
            $this->inLanguage = TRUE;
            if ( !isset($attribs['name']) ) $attribs['name'] = 'Unknown';
            if ( !isset($attribs['version']) ) $attribs['version'] = 'Unknown';
            $this->Output->writeLine($attribs['name'].' version '.$attribs['version']);
            $this->Output->addIndent();        
        }
    }

    // Closing tag handler
    function close(& $tag) {
        // Call child filter
        $this->child->close($tag);
        
        if ( $tag == 'language' ) {
            $this->inLanguage = FALSE;
            $this->Output->removeIndent();
        }
    }

    // Character data handler
    function data(& $data) {
        // Call child filter
        if ( !$this->child->data($data) ) {
            if ( $this->inLanguage ) {
                $this->Output->writeLine($data);
            }
        }
    }
}
//---------------------------------------------------------------------
// Filters for URL
class UrlFilter extends XML_SaxFilters_AbstractFilter
/* implements XML_SaxFilters_FilterInterface */
{
    /**
    * Instance of Output
    */
    var $Output;
    
    var $inUrl = FALSE;
    
    function UrlFilter(& $Output) {
        $this->Output = & $Output;
    }
   
    // Opening tag handler
    function open(& $tag,& $attribs) {       
        if ( $tag == 'url' ) {
            $this->inUrl = TRUE;
        }
    }

    // Closing tag handler
    function close(& $tag) {
        if ( $tag == 'url' ) {
            $this->inUrl = FALSE;
        }
    }

    // Character data handler
    function data(& $data) {
        if ( $this->inUrl ) {
            $this->Output->writeLine($data);
            return TRUE;
        } else {
            return FALSE;
        }
    }
}
//---------------------------------------------------------------------
// A Simple XML document
$doc = <<<EOD
<?xml version="1.0"?>
<dynamically_typed_languages>
    <language name="PHP" version="4.3.2">
        PHP is number 1 for building web based applications.
        <url>http://www.php.net</url>
    </language>
    <language name="Python" version="2.2.3">
        Python is number 1 for cross platform desktop applications.
        <url>http://www.python.org</url>
    </language>
    <language name="Perl" version="5.8.0">
        Perl is number 1 for text and batch processing.
        <url>http://www.perl.org</url>
    </language>
</dynamically_typed_languages>
EOD;

//---------------------------------------------------------------------
// This is where the action takes place

// Create the parser (use native SAX extension, StringReader, XML document)
$parser = & XML_SaxFilters_createParser('Expat','String',$doc);

// Create the instance of Output
$Output = & new Output();

// Set up the filters
$LF = & new LanguageFilter($Output);
$UF = & new UrlFilter($Output);

// Add the UrlFilter to the LanguageFilter
$LF->setChild($UF);

// Add the LanguageFilter to the parser
$parser->setChild($LF);

// Parse
if ( ! $parser->parse() ) {
    $error = $parser->getError();
    echo $error->getMessage();
} else {
    echo '<pre>'.$Output->fetch().'</pre>';
}
?>