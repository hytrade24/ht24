<?php

class TwigExtensions_Node_CodeAppendFrame extends Twig_Node {
    
    public function __construct($type, $line, $tag = null)
    {
        parent::__construct(array(), array('type' => $type), $line, $tag);
    }

    public function compile(Twig_Compiler $compiler)
    {
        $type = $this->getAttribute("type");
        $compiler->addDebugInfo($this);
        if ($type == "start") {
            $compiler->write('ob_start();')->raw("\n");
        } else {
         /*   
        if (array_key_exists('CodeAppend', $GLOBALS) && array_key_exists($name, $GLOBALS['CodeAppend'])) {
            return implode("\n", array_values($GLOBALS['CodeAppend'][$name]));
        } else {
            return "";
        }
         */
            $varName = $compiler->getVarName();
            $varContents = $compiler->getVarName();
            $varReplaceFrom = $compiler->getVarName();
            $varReplaceTo = $compiler->getVarName();
            // $varReplaceFrom = [];
            $compiler->write('$' . $varReplaceFrom . ' = [];')->raw("\n");
            // $varReplaceTo = [];
            $compiler->write('$' . $varReplaceTo . ' = [];')->raw("\n");
            // if (array_key_exists("CodeAppend", $GLOBALS)) {
            $compiler->write('if (TwigExtensions_ScriptAppend::hasBlocks()) {')->raw("\n");
            $compiler->indent();
            {
                // foreach ($GLOBALS["CodeAppend"] as $varName => $varContents) {
                $compiler->write('foreach (TwigExtensions_ScriptAppend::getBlocks() as $' . $varName . ' => $' . $varContents . ') {')->raw("\n");
                $compiler->indent();
                {
                    // $varReplaceFrom[] = "{# --- BLOCK ".$varName." --- #}";
                    $compiler->write('$' . $varReplaceFrom . '[] = "{# --- BLOCK ".$' . $varName . '." --- #}";')->raw("\n");
                    // $varReplaceTo[] = implode("\n", $varContents);
                    $compiler->write('$' . $varReplaceTo . '[] = implode("\n", $' . $varContents . ');')->raw("\n");
                }
                // }
                $compiler->outdent();
                $compiler->write('}')->raw("\n");
            }
            // }
            $compiler->outdent();
            $compiler->write('}')->raw("\n");
            // echo str_replace
            $compiler->write('echo str_replace($'.$varReplaceFrom.', $'.$varReplaceTo.', ob_get_clean());')->raw("\n");
        }
    }
    
}