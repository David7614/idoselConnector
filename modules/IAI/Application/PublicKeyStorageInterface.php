<?php
namespace app\modules\IAI\Application;

/**
 * Interface used to store and retrieve authorization server's public key in order to keep it locally instead of
 * accessing it from server every time when authorization occurs.
 */
interface PublicKeyStorageInterface
{
    /**
     * Stores public key locally on the machine where application is running
     *
     * @param string $key Authorization server's public key
     */
    public function store($key);

    /**
     * Retrieves locally stored authorization server's public key
     *
     * @return string
     */
    public function retrieve();
}