<?php

namespace Weglot\Client;

class Profile
{
    /**
     * @var int
     */
    protected $version;

    /**
     * @var int
     */
    protected $translationEngine;

    /**
     * @param string $apiKey
     * @param int    $translationEngine
     */
    public function __construct($apiKey, $translationEngine)
    {
        $this->setup($apiKey, $translationEngine);
    }

    /**
     * @param string $apiKey
     * @param int    $translationEngine
     */
    protected function setup($apiKey, $translationEngine): void
    {
        if (\is_string($apiKey) && 0 === strpos($apiKey, 'wg_') && 35 === \strlen($apiKey)) {
            $this
                ->setApiVersion(1)
                ->setTranslationEngine(1);
        } else {
            $this
                ->setApiVersion(2)
                ->setTranslationEngine($translationEngine);
        }
    }

    /**
     * @param int $version
     *
     * @return $this
     */
    public function setApiVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return int
     */
    public function getApiVersion()
    {
        return $this->version;
    }

    /**
     * @param int $translationEngine
     *
     * @return $this
     */
    public function setTranslationEngine($translationEngine)
    {
        $this->translationEngine = $translationEngine;

        return $this;
    }

    /**
     * @return int
     */
    public function getTranslationEngine()
    {
        return $this->translationEngine;
    }
}
