<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'encode',
    description: 'Encodes input image',
)]
class EncodeCommand extends Command
{
    private ?string $augliExecutable = null;
    private ?string $ffmpegExecutable = null;
    private ?string $magickExecutable = null;
    private ?string $avifdecExecutable = null;

    private ?ProcessHelper $processHelper = null;
    private OutputInterface $output;
    private SymfonyStyle $io;

    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::REQUIRED, 'Input file')
            ->addArgument('output', InputArgument::REQUIRED, 'Output file')
            ->addOption('codec', 'c', InputOption::VALUE_REQUIRED, 'Codec to use', 'avif')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);

        $this->processHelper = $this->getHelper('process');
        assert($this->processHelper instanceof ProcessHelper);

        $inputFilename = $input->getArgument('input');
        $outputFilename = $input->getArgument('output');

        $executableFinder = new ExecutableFinder();

        $this->ffmpegExecutable = $executableFinder->find('ffmpeg');
        $this->augliExecutable = $executableFinder->find('butteraugli_main.exe');
        $this->magickExecutable = $executableFinder->find('magick.exe');
        $this->avifdecExecutable = $executableFinder->find('avifdec.exe');

        // $this->findCrfForDistance($inputFilename);

        for ($transfer = 1; $transfer <= 18; $transfer++) {
            if ($transfer === 2) {
                continue;
            }

            if ($transfer === 3) {
                continue;
            }
            if ($transfer === 12) {
                continue;
            }
            if ($transfer === 17) {
                continue;
            }

            for ($primaries = 1; $primaries <= 12; $primaries++) {
                if ($primaries === 2) {
                    continue;
                }

                if ($primaries === 3) {
                    continue;
                }

                if ($primaries === 10) {
                    continue;
                }

                for ($matrix = 1; $matrix <= 10; $matrix++) {
                    if ($matrix === 2) {
                        continue;
                    }

                    if ($matrix === 3) {
                        continue;
                    }

                    $filename = sprintf('test_%d_%d_%d_full.avif', $transfer, $primaries, $matrix);
                    $this->io->writeln(sprintf('Trying transfer = %d, primaries = %d, matrix = %d, full range', $transfer, $primaries, $matrix));
                    $this->avifEncode($inputFilename, $filename, 20, $primaries, $matrix, $transfer, true);
                    $distanceScore = $this->butterAugliScore($inputFilename, 'test.avif');
                    $this->io->writeln(sprintf('Distance score: %.3f', $distanceScore));

                    if ($distanceScore <= 3) {
                        $this->io->success('Got something!!');
                    }

                    $filename = sprintf('test_%d_%d_%d_limited.avif', $transfer, $primaries, $matrix);
                    $this->io->writeln(sprintf('Trying transfer = %d, primaries = %d, matrix = %d, limited range', $transfer, $primaries, $matrix));
                    $this->avifEncode($inputFilename, $filename, 20, $primaries, $matrix, $transfer, false);
                    $distanceScore = $this->butterAugliScore($inputFilename, 'test.avif');
                    $this->io->writeln(sprintf('Distance score: %.3f', $distanceScore));

                    if ($distanceScore <= 3) {
                        $this->io->success('Got something!!');
                    }
                }
            }
        }

        return Command::SUCCESS;
    }

    protected function findCrfForDistance(string $inputFilename, float $butterAugliTarget = 2.0): int
    {
        $currentCrf = 40;
        $currentDelta = -5;

        $maxCrf = $currentCrf;
        $minCrf = 1;

        while (true) {
            $currentCrf = max(1, $currentCrf);

            $this->io->writeln(sprintf('Trying with CRF %d', $currentCrf));
            $this->avifEncode($inputFilename, 'distance.avif', $currentCrf);
            $augliScore = $this->butterAugliScore($inputFilename, 'distance.avif');

            if ($augliScore >= $butterAugliTarget) {
                $this->io->writeln(sprintf('Butteraugli distance %.3f, too high.', $augliScore));
                $maxCrf = $currentCrf;

                if ($currentDelta > 0) {
                    $this->io->writeln('Lowering CRF to increase quality');
                    $currentDelta = -$currentDelta;         // Decrease CRF
                }
            } else {
                $this->io->writeln(sprintf('Butteraugli distance %.3f, too low.', $augliScore));
                $minCrf = $currentCrf;

                if ($currentDelta < 0) {
                    $this->io->writeln('Increasing CRF to decrease quality');
                    $currentDelta = -$currentDelta;         // Increase CRF
                }
            }

            $currentCrf += $currentDelta;

            if ($currentDelta >= 0) {
                if ($currentCrf >= $maxCrf) {
                    // We already tried this.
                    if (abs($currentDelta) === 5) {
                        $this->io->writeln(sprintf('Already tried CRF %d, decreasing CRF to increase quality but in smaller steps', $currentCrf));
                        $currentDelta = -1;
                        $currentCrf--;
                    } else {
                        $this->io->writeln(sprintf('Already tried CRF %d, steps cannot be smaller. Done', $currentCrf));
                        break;
                    }
                }
            } else {
                if ($currentCrf <= $minCrf) {
                    // We already tried this.
                    if (abs($currentDelta) === 5) {
                        $this->io->writeln(sprintf('Already tried CRF %d, increasing CRF to decrease quality but in smaller steps', $currentCrf));
                        $currentDelta = 1;
                        $currentCrf++;
                    } else {
                        $this->io->writeln(sprintf('Already tried CRF %d, steps cannot be smaller. Done', $currentCrf));
                        break;
                    }
                }
            }
        }

        $this->io->info(sprintf('Done, minCrf = %d, maxCrf = %d. Pick %d', $minCrf, $maxCrf, $minCrf));

        @unlink('distance.avif');

        return $minCrf;
    }

    protected function avifEncode(
        string $inputFilename,
        string $outputFilename,
        int $crf = 23,
        int $primaries = 1,
        int $matrix = 1,
        int $transfer = 4,
        bool $fullRange = true,
    ): void {
        $ffmpegCommand = [
            $this->ffmpegExecutable,
            '-threads',
            '16',
            '-loglevel',
            'warning',
            '-i',
            $inputFilename,
            '-vf',
            sprintf(
                'format=gbrp,zscale=tin=%d:t=%d:pin=%d:p=%d:m=%d:r=%s,format=yuv444p',
                $transfer, $transfer,
                $primaries, $primaries,
                $matrix,
                $fullRange ? 'full' : 'limited',
            ),
            '-c:v',
            'libaom-av1',
            '-cpu-used',
            '4',
            '-crf',
            (string) $crf,
            '-denoise-noise-level',
            '20',
            '-tiles',
            '4x4',
            '-usage',
            'allintra',
            '-tune',
            'ssim',
            '-aom-params',
            'enable-dnl-denoising=0:sharpness=2',
            '-y',
            '-f',
            'avif',
            $outputFilename,
        ];

        $ffmpegProcess = new Process($ffmpegCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $ffmpegProcess);
    }

    protected function butterAugliScore(string $sourceFile, string $encodedFile): float
    {
        $magickCommand = [
            $this->magickExecutable,
            $encodedFile,
            '-depth',
            '8',
            '-define',
            'PNG:compression-level=0',
            'augli.png',
        ];

        $magickProcess = new Process($magickCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $magickProcess);

        // $avifdecCommand = [
        //     $this->avifdecExecutable,
        //     $encodedFile,
        //     'augli.png',
        // ];
        // $avifdecProcess = new Process($avifdecCommand, null, null, null, null);
        // $this->processHelper->mustRun($this->output, $avifdecProcess);

        $augliCommand = [
            $this->augliExecutable,
            $sourceFile,
            'augli.png',
            '--intensity_target',
            '500',
            '--pnorm',
            '6',
        ];

        $augliProcess = new Process($augliCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $augliProcess);

        $augliOutput = explode("\r\n", $augliProcess->getOutput());

        @unlink('augli.png');

        if (count($augliOutput) > 1) {
            preg_match('/^6-norm: (.*)$/', $augliOutput[1], $matches);
            if (isset($matches[1])) {
                return (float) $matches[1];
            }
        }

        throw new \RuntimeException('Could not parse output of butteraugli to get a distance score');
    }
}
