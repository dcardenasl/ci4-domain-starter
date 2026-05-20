<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

class QueueWork extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Queue';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'queue:work';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Process jobs from the queue';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'queue:work [options]';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '--queue'     => 'The queue to process (default: default)',
        '--once'      => 'Process a single job and exit',
        '--sleep'     => 'Seconds to sleep between iterations (default: 3)',
        '--max-jobs'  => 'Maximum number of jobs to process (0 = unlimited)',
        '--job-delay' => 'Seconds to wait between each processed job (default: 0)',
    ];

    /**
     * Run the command
     *
     * @param array<int, string> $params
     * @return void
     */
    public function run(array $params)
    {
        // CI4's CLI parser does not support --option=value format; it stores
        // the entire "option=value" string as the key. We resolve both formats:
        // --queue logs  (space, parsed natively)
        // --queue=logs  (equals, parsed manually)
        $queue    = $this->resolveOption('queue', 'default');
        $once     = CLI::getOption('once') !== null || $this->resolveOption('once') !== null;
        $sleep    = (int) $this->resolveOption('sleep', '3');
        $maxJobs  = (int) $this->resolveOption('max-jobs', '0');
        $jobDelay = (int) $this->resolveOption('job-delay', '0');

        $queueManager = Services::queueManager(false);
        $processedJobs = 0;

        CLI::write("Queue worker started for queue: {$queue}", 'green');

        if ($once) {
            CLI::write('Processing single job...', 'yellow');
            $processed = $queueManager->process($queue);
            CLI::write($processed ? 'Job processed' : 'No pending jobs found', $processed ? 'green' : 'yellow');
            return;
        }

        // Continuous processing
        while (true) {
            try {
                // Get stats before processing
                $stats = $queueManager->getStats($queue);

                if ($stats['pending'] > 0) {
                    CLI::write(sprintf(
                        '[%s] Processing job... (Pending: %d, Processing: %d, Failed: %d)',
                        date('Y-m-d H:i:s'),
                        $stats['pending'],
                        $stats['processing'],
                        $stats['failed']
                    ), 'yellow');

                    $queueManager->process($queue);
                    $processedJobs++;

                    CLI::write(sprintf(
                        '[%s] Job completed (Total processed: %d)',
                        date('Y-m-d H:i:s'),
                        $processedJobs
                    ), 'green');

                    if ($jobDelay > 0) {
                        sleep($jobDelay);
                    }

                    // Check if we've reached max jobs
                    if ($maxJobs > 0 && $processedJobs >= $maxJobs) {
                        CLI::write("Reached maximum jobs limit ({$maxJobs}). Exiting...", 'cyan');
                        break;
                    }
                } else {
                    // No jobs, sleep
                    if ($processedJobs > 0) {
                        CLI::write(sprintf(
                            '[%s] No pending jobs. Waiting %d seconds...',
                            date('Y-m-d H:i:s'),
                            $sleep
                        ), 'cyan');
                    }

                    sleep($sleep);
                }
            } catch (\Throwable $e) {
                CLI::error(sprintf(
                    '[%s] Error: %s',
                    date('Y-m-d H:i:s'),
                    $e->getMessage()
                ));

                // Log error
                log_message('error', 'Queue worker error: ' . $e->getMessage());

                // Continue processing after error
                sleep($sleep);
            }
        }

        CLI::write('Queue worker stopped', 'green');
    }

    /**
     * Resolve a CLI option supporting both formats:
     *   --option value   (CI4 native)
     *   --option=value   (not supported by CI4's CLI parser natively)
     *
     * @param string $name    Option name (without --)
     * @param string|null $default
     * @return string|null
     */
    private function resolveOption(string $name, ?string $default = null): ?string
    {
        $value = CLI::getOption($name);

        if ($value === null || $value === true) {
            // CI4 stores "--name=value" as the key "name=value" with a null value.
            foreach (CLI::getOptions() as $key => $val) {
                if (str_starts_with($key, "{$name}=")) {
                    return substr($key, strlen($name) + 1);
                }
            }
        }

        if ($value === true) {
            return null;
        }

        return $value ?? $default;
    }
}
