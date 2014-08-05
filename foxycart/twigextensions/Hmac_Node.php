<?php
namespace Craft;

class Hmac_Node extends \Twig_Node
{

	/**
	 * @param Twig_Compiler $compiler
	 */
	public function compile(\Twig_Compiler $compiler)
	{
		$compiler
			->addDebugInfo($this)
			->write("ob_start();\n")
			->subcompile($this->getNode('body'))
			->write("\$output = ob_get_clean();\n")
			->write("echo \FoxyCart_Helper::fc_hash_html(\$output);\n");
	}
}
