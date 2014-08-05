<?php
namespace Craft;


class Hmac_TokenParser extends \Twig_TokenParser
{
	/**
	 * @return string
	 */
	public function getTag()
	{
		return 'hmac';
	}

	/**
	 * Parses {% hmac %}...{% endhmac %} tags.
	 *
	 * @param Twig_Token $token
	 * @return Hmac_Node
	 */
	public function parse(\Twig_Token $token)
	{
		$nodes = array(
			'body' => null
		);

		$lineno = $token->getLine();
		$stream = $this->parser->getStream();

		$stream->expect(\Twig_Token::BLOCK_END_TYPE);
		$nodes['body'] = $this->parser->subparse(array($this, 'decideHmacEnd'), true);
		$stream->expect(\Twig_Token::BLOCK_END_TYPE);

		return new Hmac_Node($nodes, array(), $lineno, $this->getTag());
	}

	/**
	 * @param Twig_Token $token
	 * @return bool
	 */
	public function decideHmacEnd(\Twig_Token $token)
	{
		return $token->test('endhmac');
	}
}
