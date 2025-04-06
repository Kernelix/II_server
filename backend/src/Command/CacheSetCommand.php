<?php

namespace App\Command;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Cache\CacheItem;

#[AsCommand(
    name: 'app:cache:set',
    description: 'Sets a value in the cache'
)]
class CacheSetCommand extends Command
{
    public function __construct(
        private CacheInterface $cache
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'Cache key')
            ->addArgument('value', InputArgument::REQUIRED, 'Value to store')
            ->addArgument('ttl', InputArgument::OPTIONAL, 'Time to live in seconds');
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');
        $ttl = $input->getArgument('ttl');

        $this->cache->get($key, function (CacheItem $item) use ($value, $ttl) {
            if ($ttl) {
                $item->expiresAfter((int)$ttl);
            }
            return $value;
        });

        $output->writeln(sprintf(
            'Successfully cached value "%s" with key "%s"',
            $value,
            $key
        ));

        return Command::SUCCESS;
    }
}
