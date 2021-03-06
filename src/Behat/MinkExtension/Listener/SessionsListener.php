<?php

/*
 * This file is part of the Behat MinkExtension.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\MinkExtension\Listener;

use Behat\Behat\Tester\Event\AbstractScenarioTested;
use Behat\Behat\Tester\Event\ExampleTested;
use Behat\Behat\Tester\Event\ScenarioTested;
use Behat\Mink\Mink;
use Behat\Testwork\ServiceContainer\Exception\ProcessingException;
use Behat\Testwork\Tester\Event\ExerciseCompleted;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Mink sessions listener.
 * Listens Behat events and configures/stops Mink sessions.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class SessionsListener implements EventSubscriberInterface
{
    private $mink;
    private $defaultSession;
    private $javascriptSession;

    /**
     * Initializes initializer.
     *
     * @param Mink        $mink
     * @param string      $defaultSession
     * @param string|null $javascriptSession
     */
    public function __construct(Mink $mink, $defaultSession, $javascriptSession)
    {
        $this->mink              = $mink;
        $this->defaultSession    = $defaultSession;
        $this->javascriptSession = $javascriptSession;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScenarioTested::BEFORE   => array('prepareDefaultMinkSession', 10),
            ExampleTested::BEFORE    => array('prepareDefaultMinkSession', 10),
            ExerciseCompleted::AFTER => array('tearDownMinkSessions', -10)
        );
    }

    /**
     * Configures default Mink session before each scenario.
     * Configuration is based on provided scenario tags:
     *
     * `@javascript` tagged scenarios will get `javascript_session` as default session
     * `@mink:CUSTOM_NAME tagged scenarios will get `CUSTOM_NAME` as default session
     * Other scenarios get `default_session` as default session
     *
     * `@insulated` tag will cause Mink to stop current sessions before scenario
     * instead of just soft-resetting them
     *
     * @param AbstractScenarioTested $event
     *
     * @throws ProcessingException when the @javascript tag is used without a javascript session
     */
    public function prepareDefaultMinkSession(AbstractScenarioTested $event)
    {
        $scenario = $event->getScenario();
        $feature  = $event->getFeature();
        $session  = $this->defaultSession;

        foreach (array_merge($feature->getTags(), $scenario->getTags()) as $tag) {
            if ('javascript' === $tag) {
                if (null === $this->javascriptSession) {
                    throw new ProcessingException('The @javascript tag cannot be used without enabling a javascript session');
                }

                $session = $this->javascriptSession;
            } elseif (preg_match('/^mink\:(.+)/', $tag, $matches)) {
                $session = $matches[1];
            }
        }

        if ($scenario->hasTag('insulated') || $feature->hasTag('insulated')) {
            $this->mink->stopSessions();
        } else {
            $this->mink->resetSessions();
        }

        $this->mink->setDefaultSessionName($session);
    }

    /**
     * Stops all started Mink sessions.
     */
    public function tearDownMinkSessions()
    {
        $this->mink->stopSessions();
    }
}
