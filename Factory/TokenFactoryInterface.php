<?php

namespace Yokai\SecurityTokenBundle\Factory;

use Yokai\SecurityTokenBundle\Entity\Token;

/**
 * @author Yann Eugoné <eugone.yann@gmail.com>
 */
interface TokenFactoryInterface
{
    /**
     * @param mixed  $user
     * @param string $purpose
     * @param array  $payload
     *
     * @return Token
     */
    public function create($user, $purpose, array $payload = []);
}
