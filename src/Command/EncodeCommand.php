<?php

namespace App\Command;

use App\ImageQualityIterator;
use App\IteratorDirection;
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

use function Symfony\Component\String\u;

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
            ->addOption('distance', 'd', InputOption::VALUE_REQUIRED, 'Butteraugli distance target', '2.0')
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
        $codec = $input->getOption('codec');
        $distanceTarget = (float) $input->getOption('distance');

        $executableFinder = new ExecutableFinder();

        $this->augliExecutable = $executableFinder->find('butteraugli_main.exe');
        $this->magickExecutable = $executableFinder->find('magick.exe');
        switch ($codec) {
            case 'avif':
                $this->ffmpegExecutable = $executableFinder->find('ffmpeg');
                break;
            case 'avifenc':
                $this->avifencExecutable = $executableFinder->find('avifenc.exe');
                break;
            case 'jxl':
                $this->cjxlExecutable = $executableFinder->find('cjxl.exe');
                $this->djxlExecutable = $executableFinder->find('djxl.exe');
                break;
            case 'heic':
                // FIXME:
                $this->io->error('Still todo');
                break;
            case 'jpeg':
                $this->cjpegExecutable = $executableFinder->find('cjpeg-static.exe');
                break;
            case 'webp':
                $this->cwebpExecutable = $executableFinder->find('cwebp.exe');
                $this->dwebpExecutable = $executableFinder->find('dwebp.exe');
                break;
            default:
                $this->io->error('Unknown codec!');

                return self::FAILURE;
        }

        $stepper = match ($codec) {
            'avif' => new ImageQualityIterator(1, 50, 0, $distanceTarget),
            'avifenc' => new ImageQualityIterator(50, 100, 0, $distanceTarget, IteratorDirection::INCREASE),
            'jxl' => new ImageQualityIterator(0.1, 5, 1, $distanceTarget),
            'heic' => new ImageQualityIterator(1, 50, 0, $distanceTarget),
            'jpeg' => new ImageQualityIterator(1, 100, 0, $distanceTarget, IteratorDirection::INCREASE),
            'webp' => new ImageQualityIterator(1, 100, 0, $distanceTarget, IteratorDirection::INCREASE),
        };

        while (!$stepper->iterate(function (float $qualityValue) use ($codec, $inputFilename, $outputFilename): float {
            $this->io->writeln(sprintf('Trying with %.3f', $qualityValue));

            switch ($codec) {
                case 'avif':
                    $this->avifFFEncode($inputFilename, $outputFilename, (int) $qualityValue);
                    break;
                case 'avifenc':
                    $this->avifEncEncode($inputFilename, $outputFilename, (int) $qualityValue);
                    break;
                case 'jxl':
                    $this->cjxlEncode($inputFilename, $outputFilename, $qualityValue);
                    break;
                case 'heic':
                    // FIXME:
                    break;
                case 'jpeg':
                    $this->mozjpegEncode($inputFilename, $outputFilename, (int) $qualityValue);
                    break;
                case 'webp':
                    $this->webpEncode($inputFilename, $outputFilename, (int) $qualityValue);
                    break;
            }

            $score = $this->butterAugliScore($inputFilename, $outputFilename);
            $this->io->writeln(sprintf('Score: %.3f', $score));

            return $score;
        })) {
        }

        $this->io->writeln(sprintf('Settled on %.3f', $stepper->getResult()));

        return Command::SUCCESS;
    }

    protected function avifFFEncode(
        string $inputFilename,
        string $outputFilename,
        int $crf = 23,
        int $primaries = 1,
        int $matrix = 7,
        int $transfer = 13,
        bool $fullRange = true,
    ): void {
        $ffmpegCommand = [
            $this->ffmpegExecutable,
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
            '5x5',
            '-row-mt',
            '1',
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
            '4',
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
            $outputFilename,
        ];

        $cwebpProcess = new Process($cwebpCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $cwebpProcess);
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
            $outputFilename,
        ];

        $cjxlProcess = new Process($cjxlCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $cjxlProcess);
    }

    protected function mozjpegEncode(string $inputFilename, string $outputFilename, int $quality): void
    {
        $magickCommand = [
            $this->magickExecutable,
            $inputFilename,
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
            $outputFilename,
        ];

        $cjpegProcess = new Process($cjpegCommand, null, null, $pnmData, null);
        $this->processHelper->mustRun($this->output, $cjpegProcess);
    }

    protected function butterAugliScore(string $sourceFile, string $encodedFile): float
    {
        $encodedString = u($encodedFile);

        if ($encodedString->endsWith('.jxl')) {
            $djxlCommand = [
                $this->djxlExecutable,
                $encodedFile,
                'augli.ppm',
            ];

            $djxlProcess = new Process($djxlCommand, null, null, null, null);
            $this->processHelper->mustRun($this->output, $djxlProcess);

            $augliFile = 'augli.ppm';
        } elseif ($encodedString->endsWith('.webp')) {
            $dwebpCommand = [
                $this->dwebpExecutable,
                $encodedFile,
                '-ppm',
                '-o',
                'augli.ppm',
            ];

            $dwebpProcess = new Process($dwebpCommand, null, null, null, null);
            $this->processHelper->mustRun($this->output, $dwebpProcess);

            $augliFile = 'augli.ppm';
        } else {
            $magickCommand = [
                $this->magickExecutable,
                $encodedFile,
                '-depth',
                '8',
                'augli.pnm',
            ];

            $magickProcess = new Process($magickCommand, null, null, null, null);
            $this->processHelper->mustRun($this->output, $magickProcess);

            $augliFile = 'augli.pnm';
        }

        $augliCommand = [
            $this->augliExecutable,
            $sourceFile,
            $augliFile,
            '--intensity_target',
            '500',
            '--pnorm',
            '6',
        ];

        $augliProcess = new Process($augliCommand, null, null, null, null);
        $this->processHelper->mustRun($this->output, $augliProcess);

        $augliOutput = explode("\r\n", $augliProcess->getOutput());

        @unlink($augliFile);

        if (count($augliOutput) > 1) {
            preg_match('/^6-norm: (.*)$/', $augliOutput[1], $matches);
            if (isset($matches[1])) {
                return (float) $matches[1];
            }
        }

        throw new \RuntimeException('Could not parse output of butteraugli to get a distance score');
    }
}
