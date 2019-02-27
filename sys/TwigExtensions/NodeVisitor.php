<?php

class TwigExtensions_NodeVisitor implements \Twig_NodeVisitorInterface {

    /**
     * Called before child nodes are visited.
     *
     * @return Twig_NodeInterface The modified node
     */
    public function enterNode(\Twig_NodeInterface $node, Twig_Environment $env)
    {
        // Nothing
        if ($node instanceof \Twig_Node_Module) {
            $nodeDisplayStart = $node->getNode("display_start");
            $nodeDisplayEnd = $node->getNode("display_end");
            // Add hooks to the beginning and the end of the template rendering
            $nodeDisplayStart->setNode( $nodeDisplayStart->count(), new TwigExtensions_Node_CodeAppendFrame("start", $node->getLine()));
            $nodeDisplayEnd->setNode( $nodeDisplayEnd->count(), new TwigExtensions_Node_CodeAppendFrame("end", $node->getLine()));
        }
        return $node;
    }

    /**
     * Called after child nodes are visited.
     *
     * @return Twig_NodeInterface|false The modified node or false if the node must be removed
     */
    public function leaveNode(\Twig_NodeInterface $node, Twig_Environment $env)
    {
        return $node;
    }

    /**
     * Returns the priority for this visitor.
     *
     * Priority should be between -10 and 10 (0 is the default).
     *
     * @return int The priority level
     */
    public function getPriority()
    {
        return 0;
    }
}