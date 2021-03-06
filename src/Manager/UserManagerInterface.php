<?php

namespace Yokai\SecurityTokenBundle\Manager;

/**
 * A user manager is the entry point to deal with users that could be attached to tokens.
 *
 * @author Yann Eugoné <eugone.yann@gmail.com>
 */
interface UserManagerInterface
{
    /**
     * Tell whether or not the manager is supporting a user class.
     *
     * @param string $class The user class
     *
     * @return boolean
     */
    public function supportsClass(string $class): bool;

    /**
     * Tell whether or not the manager is supporting a user.
     *
     * @param mixed $user The user
     *
     * @return boolean
     */
    public function supportsUser($user): bool;

    /**
     * Get user of certain class with certain id.
     *
     * @param string $class The user class
     * @param string $id    The user id
     *
     * @return mixed
     */
    public function get(string $class, string $id);

    /**
     * Get the class of a user.
     *
     * @param mixed $user The user
     *
     * @return string
     */
    public function getClass($user): string;

    /**
     * Get the id of a user.
     *
     * @param mixed $user The user
     *
     * @return string
     */
    public function getId($user): string;
}
