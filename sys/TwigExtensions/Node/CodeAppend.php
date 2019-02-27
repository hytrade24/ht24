<?php

class TwigExtensions_Node_CodeAppend extends Twig_Node {
    
    public function __construct($name, $ident, Twig_Node $value, $line, $tag = null)
    {
        parent::__construct(array('value' => $value), array('name' => $name, 'ident' => $ident), $line, $tag);
    }

    public function compile(Twig_Compiler $compiler)
    {
        #die(var_dump($this->getNode("value")));
        $bodyNode = $this->getNode('value');
        $compiler
            ->addDebugInfo($this)
            ->write('ob_start();')->subcompile($bodyNode)
            ->write('TwigExtensions_ScriptAppend::addBlock("'.$this->getAttribute('name').'", ');
        if ($this->getAttribute("ident") instanceof Twig_Node) {
            $compiler->subcompile($this->getAttribute("ident"));
        } else {
            $compiler->repr($this->getAttribute("ident"));
        }
        $compiler->raw(', ob_get_clean());')->raw("\n");
        /*
            ->write('$codeAppendIdent = ')->subcompile($this->getNode("ident"))->raw(";\n")
            ->write('if (!array_key_exists(\'CodeAppend\', $GLOBALS)) { $GLOBALS[\'CodeAppend\'] = array(); }')->raw("\n")
            ->write('if (!array_key_exists(\''.$this->getAttribute('name').'\', $GLOBALS[\'CodeAppend\'])) { $GLOBALS[\'CodeAppend\'][\''.$this->getAttribute('name').'\'] = array(); }')->raw("\n")
            ->write('if (!array_key_exists($codeAppendIdent, $GLOBALS[\'CodeAppend\'][\''.$this->getAttribute('name').'\'])) {')->raw("\n")
                ->write('ob_start();')->subcompile($bodyNode)
                ->write('$GLOBALS[\'CodeAppend\'][\''.$this->getAttribute('name').'\'][$codeAppendIdent] = ob_get_clean()')->raw(";\n")
            ->raw("}\n")
        ;
        */
        #die($compiler->getSource());
    }
    
}