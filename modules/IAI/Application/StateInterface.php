<?php

namespace app\modules\IAI\Application;

/**
 * Interface used to set and retrieve current application state in order to fulfill OAuth2 security standards
 */
interface StateInterface
{
    /**
     * Sets current application state
     *
     * @param string $state New state
     */
    public function setState($state);

    /**
     * Retrieves current application state
     *
     * @return string
     */
    public function getCurrentState();

    /**
     * Creates CSRF state token used while authorizing over OAuth 2.
     * This string will be set as a new application state by setSate method.
     *
     * @return string
     */
    public function createAuthenticationStateString();
}