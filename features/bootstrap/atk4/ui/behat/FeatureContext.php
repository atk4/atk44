<?php

declare(strict_types=1);

namespace atk4\ui\behat;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Testwork\Tester\Result\TestResult;

/**
 * Dump page data when failed.
 */
class FeatureContext extends FeatureContextBasic
{
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    /**
     * Dump current page data when step failed to allow easy debug on TravisCI.
     *
     * @AfterStep
     */
    public function dumpPageAfterFailedStep(AfterStepScope $event)
    {
        if ($event->getTestResult()->getResultCode() === TestResult::FAILED) {
            if ($this->getSession()->getDriver() instanceof \Behat\Mink\Driver\Selenium2Driver) {
                echo 'Dump of failed step:' . "\n";
                echo 'Current page URL: ' . $this->getSession()->getCurrentUrl() . "\n";
                global $dumpPageCount;
                if (++$dumpPageCount <= 1) { // prevent huge tests output
                    // upload screenshot here if needed in the future
                    // $screenshotData = $this->getSession()->getScreenshot();
                    // echo 'Screenshot URL: ' . $screenshotUrl . "\n";
                    echo 'Page source: ' . $this->getSession()->getPage()->getContent() . "\n";
                } else {
                    echo 'Page source: Source code is dumped for the first failed step only.' . "\n";
                }
            }
        }
    }
}
