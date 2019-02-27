<?php

class TwigExtensions_TokenParser_CodeAppend extends Twig_TokenParser {

    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     *
     * @throws Twig_Error_Syntax
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        
        $name = $stream->expect(Twig_Token::STRING_TYPE)->getValue();
        if (!$stream->test(Twig_Token::BLOCK_END_TYPE)) {
            $identExpr = $this->parser->getExpressionParser()->parseExpression();
        } else {
            $identExpr = md5(microtime());
        }
        #$ident = $stream->expect(Twig_Token::STRING_TYPE)->getValue();
        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'parseBody'], true);
        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        
        return new TwigExtensions_Node_CodeAppend($name, $identExpr, $body, $lineno, $this->getTag());        
    }

    public function parseBody(Twig_Token $token)
    {
        return $token->test(array('endcodeappend'));
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return "codeappend";
    }
}