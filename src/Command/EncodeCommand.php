<?php

namespace App\Command;

use App\QualityStepper;
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
    // private ?string $avifdecExecutable = null;
    private ?string $avifencExecutable = null;
    private ?string $cjpegExecutable = null;
    private ?string $cjxlExecutable = null;
    private ?string $djxlExecutable = null;
    private ?string $cwebpExecutable = null;
    private ?string $dwebpExecutable = null;

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
        // $this->avifdecExecutable = $executableFinder->find('avifdec.exe');
        $this->avifencExecutable = $executableFinder->find('avifenc.exe');
        $this->cjpegExecutable = $executableFinder->find('cjpeg-static.exe');
        $this->cjxlExecutable = $executableFinder->find('cjxl.exe');
        $this->djxlExecutable = $executableFinder->find('djxl.exe');
        $this->cwebpExecutable = $executableFinder->find('cwebp.exe');
        $this->dwebpExecutable = $executableFinder->find('dwebp.exe');

        // $stepper = new QualityStepper(5.0, 1.0, 40.0, 1.0, QualityStepper::INCREASE);
        // while ($stepper->iterate(function (float $value) use ($inputFilename) {
        //     $this->avifFFEncode($inputFilename, 'distance.avif', (int) $value, 1, 1, 13, true);

        //     return $this->butterAugliScore($inputFilename, 'distance.avif');
        // })) {
        // }

        // $this->io->info(sprintf('Value we ended up with: %d %d', (int) $stepper->minValueReached, (int) $stepper->maxValue));

        $stepper = new QualityStepper(5.0, 1.0, 99.0, 50.0, QualityStepper::DECREASE);
        while ($stepper->iterate(function (float $value) {
            // $this->avifEncEncode($inputFilename, 'distance.avif', (int) $value);
            // $this->mozjpegEncode($inputFilename, 'distance.jpeg', (int) $value);

            // return $this->butterAugliScore($inputFilename, 'distance.jpeg');

            return $this->fakeEncode((int) $value);
        })) {
        }

        $this->io->info(sprintf('Value we ended up with: %d %d', (int) $stepper->minValueReached, (int) $stepper->maxValue));

        return Command::SUCCESS;
    }

    protected function avifFFEncode(
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
            '6',
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

    protected function avifEncEncode(
        string $inputFilename,
        string $outputFilename,
        int $quality = 65,
    ): void {
        $avifencCommand = [
            $this->avifencExecutable,
            '-j',
            'all',
            '-s',
            '6',
            '-y',
            '444',
            '--autotiling',
            '-a',
            'tune=ssim',
            '-a',
            'sharpness=2',
            '-a',
            'denoise-noise-level=20',
            '-a',
            'enable-dnl-denoising=0',
            '-q',
            (string) $quality,
            $inputFilename,
            $outputFilename,
        ];

        $avifencProcess = new Process($avifencCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $avifencProcess);
    }

    protected function webpEncode(
        string $inputFilename,
        string $outputFilename,
        int $quality = 65,
    ): void {
        $cwebpCommand = [
            $this->cwebpExecutable,
            '-q',
            (string) $quality,
            // '-preset',
            // 'photo',
            '-m',
            '6',
            '-sns',
            '100',
            '-sharpness',
            '7',
            '-mt',
            '-noalpha',
            $inputFilename,
            '-o',
            'cwebp.webp',
        ];

        $cwebpProcess = new Process($cwebpCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $cwebpProcess);

        $dwebpCommand = [
            $this->dwebpExecutable,
            'cwebp.webp',
            '-ppm',
            '-o',
            $outputFilename,
        ];

        $dwebpProcess = new Process($dwebpCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $dwebpProcess);

        // @unlink('cwebp.webp');
    }

    protected function cjxlEncode(
        string $inputFilename,
        string $outputFilename,
        float $distance = 1.8,
    ): void {
        $cjxlCommand = [
            $this->cjxlExecutable,
            '-e',
            '8',
            '-d',
            sprintf('%.1f', $distance),
            '--intensity_target',
            '500',
            $inputFilename,
            'cjxl.jxl',
        ];

        $cjxlProcess = new Process($cjxlCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $cjxlProcess);

        $djxlCommand = [
            $this->djxlExecutable,
            'cjxl.jxl',
            $outputFilename,
        ];

        $djxlProcess = new Process($djxlCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $djxlProcess);

        // @unlink('cjxl.jxl');
    }

    protected function fakeEncode(int $quality): float
    {
        return match ($quality) {
            50 => 4.6,
            55 => 4.0,
            60 => 3.7,
            65 => 3.3,
            70 => 2.9,
            75 => 2.5,
            80 => 2.2,
            81 => 2.15,
            82 => 2.1,
            83 => 2.05,
            84 => 1.95,
            85 => 1.9,
        };
    }

    protected function mozjpegEncode(string $sourceFile, string $encodedFile, int $quality): void
    {
        $magickCommand = [
            $this->magickExecutable,
            $sourceFile,
            '-depth',
            '8',
            'pnm:-',
        ];

        $magickProcess = new Process($magickCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $magickProcess);

        $pnmData = $magickProcess->getOutput();
        unset($magickProcess);

        $cjpegCommand = [
            $this->cjpegExecutable,
            '-quality',
            (string) $quality,
            '-sample',
            '1x1',
            '-outfile',
            $encodedFile,
        ];

        $cjpegProcess = new Process($cjpegCommand, null, null, $pnmData, null);
        $this->processHelper->mustRun($this->output, $cjpegProcess);
    }

    protected function butterAugliScore(string $sourceFile, string $encodedFile): float
    {
        $magickCommand = [
            $this->magickExecutable,
            $encodedFile,
            '-depth',
            '8',
            'augli.pnm',
        ];

        $magickProcess = new Process($magickCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $magickProcess);

        $augliCommand = [
            $this->augliExecutable,
            $sourceFile,
            'augli.pnm',
            '--intensity_target',
            '500',
            '--pnorm',
            '6',
        ];

        $augliProcess = new Process($augliCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $augliProcess);

        $augliOutput = explode("\r\n", $augliProcess->getOutput());

        @unlink('augli.pnm');

        if (count($augliOutput) > 1) {
            preg_match('/^6-norm: (.*)$/', $augliOutput[1], $matches);
            if (isset($matches[1])) {
                return (float) $matches[1];
            }
        }

        throw new \RuntimeException('Could not parse output of butteraugli to get a distance score');
    }
}
