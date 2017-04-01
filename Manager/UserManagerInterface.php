<?php

namespace Yokai\SecurityTokenBundle\Manager;

/**
 * @author Yann Eugoné <eugone.yann@gmail.com>
 */
interface UserManagerInterface
{
    /**
     * @param string $class
     * @param string $id
     *
     * @return mixed
     */
    public function get($class, $id);

    /**
     * @param mixed $user
     *
     * @return string
     */
    public function getClass($user);

    /**
     * @param mixed $user
     *
     * @return string
     */
    public function getId($user);
}