<?php

namespace OCA\TickyCRM\Command;

use OCA\TickyCRM\Service\DevSeedService;
use OCP\IUserManager;
use OCP\IUserSession;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DevSeed extends Command {
    public function __construct(private DevSeedService $seeder, private IUserManager $users, private IUserSession $session) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('ticky_crm:dev:seed')
            ->setDescription('Create development customers, products and customer-product links without replacing existing data.')
            ->addOption('clients', null, InputOption::VALUE_REQUIRED, 'Number of customers (1-1000)', '24')
            ->addOption('products', null, InputOption::VALUE_REQUIRED, 'Number of catalog products (1-1000)', '12')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Unique uppercase seed prefix (maximum 24 characters)', 'DEV')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Existing Nextcloud user for activity attribution', 'admin')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate options and number availability and show counts without writing data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $previousUser = $this->session->getUser();
        try {
            $counts = [];
            foreach (['clients', 'products'] as $field) {
                $value = $input->getOption($field);
                if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,3}$/D', $value) || (int)$value > 1000) {
                    throw new \InvalidArgumentException('--' . $field . ' must be an integer between 1 and 1000.');
                }
                $counts[$field] = (int)$value;
            }
            $user = $this->users->get((string)$input->getOption('user'));
            if ($user === null) throw new \InvalidArgumentException('User does not exist. Specify an existing --user.');
            $this->session->setUser($user);
            $dryRun = (bool)$input->getOption('dry-run');
            $result = $this->seeder->seed($counts['clients'], $counts['products'], (string)$input->getOption('prefix'), $dryRun);
            $output->writeln(sprintf('%s: %d customers, %d products, %d customer-product links. Prefix: %s', $dryRun ? 'Dry run' : 'Created', $result['clients'], $result['products'], $result['links'], $input->getOption('prefix')));
            return 0;
        } catch (\Throwable $error) {
            $output->writeln('<error>' . \Symfony\Component\Console\Formatter\OutputFormatter::escape($error->getMessage()) . '</error>');
            return 1;
        } finally {
            $this->session->setUser($previousUser);
        }
    }
}
