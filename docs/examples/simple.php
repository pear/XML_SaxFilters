<?php
/**
* Shows simple usage of SaxFilters, using a single custom filter
*/
# require_once 'XML/SaxFilters.php'; // This is the normal way to do it

# Done to help development
if ( !@include_once 'XML/SaxFilters.php' ) {
    define('XML_SAXFILTERS', '../../');
    include_once XML_SAXFILTERS . 'SaxFilters.php';
}

//---------------------------------------------------------------------
// Define a customer handler class - just displays stuff
class SimpleFilter extends XML_SaxFilters_AbstractFilter
/* implements XML_SaxFilters_FilterInterface */
{
    // Parsed output stored here
    var $output = '';
    
    // For whitespace indentation
    var $indent = '';

    // Called when parsing starts
    function startDoc()
    {
        $this->output.="Parsing started\n";
    }
    
    // Opening tag handler
    function open(& $tag,& $attribs)
    {
        $this->output.=$this->indent.$tag;
        $sep = '';
        if ( count($attribs) > 0 )
        {
            $this->output.=' (';
            foreach ( $attribs as $key => $value )
            {
                $this->output.="$sep$key: $value";
                $sep = ', ';
            }
            $this->output.=')';
        }
        $this->output.="\n";
        $this->addIndent();
    }

    // Closing tag handler
    function close(& $tag)
    {
        $this->removeIndent();    
        $this->output.=$this->indent.$tag."\n";
    }

    // Character data handler
    function data(& $data)
    {
        $data = trim($data);
        if ( !empty($data) ) {
            $this->output.=$this->indent.$data."\n";
        }
    }
    
    // Called at end of parsing
    function endDoc()
    {
        $this->output.="Parsing finished\n";
    }
    
    function addIndent()
    {
        $this->indent.="\t";
    }
    function removeIndent()
    {
        $this->indent = substr_replace($this->indent,'',0,1);
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

// This uses PEAR::XML_HTMLSax instead
// $parser = & XML_SaxFilters_createParser('HTMLSax','String',$doc);

// Instantiate the filter above
$filter = & new SimpleFilter();

// Add the filter to the parser
$parser->setChild($filter);

// Parse
if ( ! $parser->parse() ) {
    $error = $parser->getError();
    echo $error->getMessage();
} else {
    echo '<pre>'.$filter->output.'</pre>';
}
?>