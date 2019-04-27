<?php
namespace Skwirrel\Pim\Console;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class Progress
{
    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var \Symfony\Component\Console\Helper\ProgressBar[]
     */
    protected $progressBars = [];

    /**
     * Set console output used for import progress
     *
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Set console output used for import progress
     *
     * @param OutputInterface $output
     */
    public function info($message)
    {
        if (isset($this->output)) {
            $this->output->writeln('<info>' . $message . '</info>');
        }
    }

    /**
     * Start a console progress bar
     *
     * @param string $name
     * @param int $steps
     */
    public function barStart($name, $steps)
    {
        if (isset($this->output)) {
            // Create progress bar
            $progressBar = new ProgressBar($this->output, $steps);
            $progressBar->start();
            $this->progressBars[$name] = $progressBar;
        }
    }

    /**
     * Advance specified console progress bar
     *
     * @param string $name
     * @return void
     */
    public function barAdvance($name)
    {
        if (array_key_exists($name, $this->progressBars)) {
            $this->progressBars[$name]->advance();
        }
    }

    /**
     * Finish specified console progress bar
     *
     * @param string $name
     * @return void
     */
    public function barFinish($name)
    {
        if (array_key_exists($name, $this->progressBars)) {
            $this->progressBars[$name]->finish();
            $this->output->writeln('');
        }
    }
}
