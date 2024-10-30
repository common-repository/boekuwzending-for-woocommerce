<?php

namespace Boekuwzending\WooCommerce\Method;

/**
 * Interface MethodInterface
 */
interface MethodInterface
{
    /**
     * @return string
     */
    public function getDefaultTitle(): string;

    /**
     * @return string
     */
    public function getSettingsDescription(): string;
}