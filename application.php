#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

class Main extends Command
{
    protected static $defaultName = 'app:main';

    protected function configure()
    {
        $this
            ->addArgument('api-url', InputArgument::REQUIRED, 'The api url to fetch')
            ->addOption('output-directory', null, InputOption::VALUE_REQUIRED, 'Output directory', '.')
            ->addOption('output-filename', null, InputOption::VALUE_REQUIRED,
                'Output filename (relative to output directory)')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Base url in converted content')
            ->addOption('file-url-pattern', null, InputOption::VALUE_REQUIRED, 'File url pattern (regexp)');
    }

    /** @var SymfonyStyle */
    private $io;

    /** @var string */
    private $baseUrl;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $apiUrl = $input->getArgument('api-url');
        $this->io = new SymfonyStyle($input, $output);
        $this->fetchData($apiUrl);

        $fileUrlPattern = $input->getOption('file-url-pattern');
        if (null !== $fileUrlPattern) {
            $this->fetchFiles($fileUrlPattern);
        }

        $this->filenames = array_combine(array_keys($this->data),
            array_map([$this, 'getFilename'], array_keys($this->data)));
        $outputFilename = $input->getOption('output-filename');
        if (null !== $outputFilename) {
            $this->filenames[$apiUrl] = $outputFilename;
        }

        // Save files
        $filesystem = new Filesystem();
        $outputDirectory = $input->getOption('output-directory');
        $this->baseUrl = rtrim($input->getOption('base-url'), '/');
        foreach ($this->filenames as $url => $filename) {
            if (isset($this->data[$url])) {
                $data = $this->data[$url];
                if (is_array($data)) {
                    $data = $this->replaceUrls($this->data[$url]);
                    $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
                $filename = $outputDirectory . '/' . $filename;
                $filesystem->mkdir(dirname($filename));
                $this->io->writeln(sprintf('Dumping content from %s to file %s', $url, $filename));
                $filesystem->dumpFile($filename, $data);
            }
        }

        return self::SUCCESS;
    }

    private function replaceUrls($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'replaceUrls'], $data);
        } elseif (is_string($data) && isset($this->filenames[$data])) {
            return ($this->baseUrl ? $this->baseUrl . '/' : '') . $this->filenames[$data];
        }
        return $data;
    }

    /** @var HttpClient */
    private $client;

    /** @var array */
    private $data = [];

    /** @var array */
    private $filenames = [];

    private function fetchFiles(string $pattern)
    {
        if (null === $this->client) {
            $this->client = HttpClient::create();
        }
        foreach ($this->data as $data) {
            if (is_array($data)) {
                array_walk_recursive($data, function ($value) use ($pattern) {
                    if (is_string($value) && preg_match($pattern, $value) && !isset($this->data[$value])) {
                        $this->io->writeln(sprintf('Fetching file %s', $value));
                        $response = $this->client->request('GET', $value);
                        $this->data[$value] = $response->getContent();
                    }
                });
            }
        }
    }

    private function fetchData(string $url)
    {
        if (isset($this->data[$url])) {
            return;
        }
        if (null === $this->client) {
            $this->client = HttpClient::create();
        }
        $this->io->writeln(sprintf('Fetching data from %s', $url));
        $response = $this->client->request('GET', $url);
        $this->data[$url] = $response->toArray();
        $this->processItem($this->data[$url]);
    }

    private function getFilename(string $url)
    {
        $parts = parse_url($url);
        $filename = ltrim($parts['path'], '/');
        if (isset($parts['query'])) {
            $filename .= '?' . $parts['query'];
        }

        // Sanitize the filename
        $filename = preg_replace('@[^a-z0-9_/]@i', '@', $filename);

        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100) . '-' . sha1($filename);
        }

        $allowedExtensions = [
            'png',
            'jpg',
        ];
        $extension = [$allowedExtensions][pathinfo($url, PATHINFO_EXTENSION)] ?? null;
        $filename .= '.' . ($extension ?: 'json');

        return $filename;
    }

    private function processItem(array $item)
    {
        if (isset($item['links'])) {
            foreach ($item['links'] as $link) {
                if (isset($link['href'])) {
                    $this->fetchData($link['href']);
                }
            }
        }
        if (isset($item['data'])) {
            if ($this->isAssoc($item['data'])) {
                $this->processItem($item['data']);
            } else {
                foreach ($item['data'] as $child) {
                    $this->processItem($child);
                }
            }
        }
    }

    // @see https://stackoverflow.com/a/173479
    private function isAssoc(array $arr)
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

$application = new Application();
$application->add(new Main());
$application->setDefaultCommand('app:main');
$application->run();

