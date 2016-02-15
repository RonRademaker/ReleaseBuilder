<?php

namespace RonRademaker\ReleaseBuilder\Modifier;

use Funivan\PhpTokenizer\Collection;
use Funivan\PhpTokenizer\Query\Query;
use Funivan\PhpTokenizer\Token;

/**
 * ConstantModifier is able to change the value of a PHP constant in a class
 *
 * @author Ron Rademaker
 */
class ConstantModifier implements ModifierInterface
{
    /**
     * The input source
     *
     * @var string
     */
    private $source;

    /**
     * Create a modifier to modify $source
     *
     * @param string $source
     */
    public function __construct($source)
    {
        $this->source = $source;
    }

    /**
     * Updates the value of the constant named $key to $value
     *
     * @param mixed $key
     * @param mixed $value
     * @return string
     */
    public function modify($key, $value)
    {
        $tokens = Collection::createFromString($this->source);
        $query = new Query();
        $query->typeIs(T_CONST);

        $constants = $tokens->find($query);

        foreach ($constants as $constant) {
            $this->handleConstant($tokens, $constant, $key, $value);
        }

        return $tokens->assemble();
    }

    /**
     * If $constant is the start of the constant defining $key, updat its value to $value
     *
     * @param Collection $tokens
     * @param Token $constant
     * @param string $key
     * @param mixed $value
     */
    private function handleConstant(Collection $tokens, Token $constant, $key, $value)
    {
        $lineEnd = false;
        $found = false;
        $index = $constant->getIndex();
        $tokens->rewind();
        while ($lineEnd === false && $constantToken = $tokens->getNext($index)) {
            if ($constantToken->getValue() === ';') {
                $lineEnd = true;
            }

            if ($constantToken->getType() === T_STRING && $constantToken->getValue() === $key) {
                $found = true;
            } elseif ($found === true) {
                $this->attemptUpdate($constantToken, $value);
            }

            $index++;
        }
    }

    /**
     * If $constantToken is the token holding the value of the constant, update the value to $value
     *
     * @param Token $constantToken
     * @param mixed $value
     */
    private function attemptUpdate(Token $constantToken, $value)
    {
        $type = $constantToken->getType();
        
        if ($type === T_CONSTANT_ENCAPSED_STRING) {
            $constantToken->setValue(
                sprintf("'%s'", $value)
            );
        } elseif ($type === T_LNUMBER || $type === T_ARRAY) {
            $constantToken->setValue($value);
        }
    }
}
