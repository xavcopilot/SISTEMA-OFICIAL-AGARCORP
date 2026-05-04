<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class LibreOfficePdfConverter
{
    private const DEFAULT_TIMEOUT_SECONDS = 120;

    public function convertSpreadsheetToPdf(string $xlsxPath, string $pdfPath, string $outputDir, array $context = []): bool
    {
        $binary = $this->resolveBinary();

        if ($binary === null) {
            Log::warning('No se encontro binario de LibreOffice para conversion PDF.', [
                ...$context,
                'xlsx' => $xlsxPath,
            ]);

            return false;
        }

        $isWindows = $this->isWindows();
        $runtimePaths = null;
        $profileDir = null;

        $command = [
            $binary,
            '--headless',
            '--nologo',
            '--nodefault',
            '--norestore',
            '--nolockcheck',
            '--nofirststartwizard',
            '--convert-to',
            'pdf:calc_pdf_Export',
            '--outdir',
            $outputDir,
            $xlsxPath,
        ];

        if (! $isWindows) {
            $runtimePaths = $this->prepareRuntimePaths();
            $profileDir = $runtimePaths['profiles'] . DIRECTORY_SEPARATOR . 'profile-' . bin2hex(random_bytes(6));

            if (! is_dir($profileDir) && ! @mkdir($profileDir, 0775, true) && ! is_dir($profileDir)) {
                Log::warning('No se pudo crear perfil runtime de LibreOffice.', [
                    ...$context,
                    'profile_dir' => $profileDir,
                    'xlsx' => $xlsxPath,
                ]);

                return false;
            }

            array_splice($command, 7, 0, ['-env:UserInstallation=' . $this->toFileUrl($profileDir)]);
        }

        $process = new Process($command);

        if ($runtimePaths !== null) {
            $process->setEnv($this->buildProcessEnvironment($runtimePaths));
        }

        $process->setTimeout($this->resolveTimeoutSeconds());
        $process->run();

        try {
            if (! $process->isSuccessful()) {
                Log::warning('Fallo conversion con LibreOffice.', [
                    ...$context,
                    'binary' => $binary,
                    'exit_code' => $process->getExitCode(),
                    'xlsx' => $xlsxPath,
                    'output_dir' => $outputDir,
                    'error' => $process->getErrorOutput(),
                    'output' => $process->getOutput(),
                ]);

                return false;
            }

            $generatedPdfPath = $this->resolveGeneratedPdfPath($outputDir, $xlsxPath);

            if ($generatedPdfPath === null || ! file_exists($generatedPdfPath)) {
                Log::warning('LibreOffice termino sin generar PDF esperado.', [
                    ...$context,
                    'binary' => $binary,
                    'exit_code' => $process->getExitCode(),
                    'xlsx' => $xlsxPath,
                    'expected_pdf' => $outputDir . DIRECTORY_SEPARATOR . pathinfo($xlsxPath, PATHINFO_FILENAME) . '.pdf',
                    'pdf_candidates' => $this->listPdfCandidates($outputDir),
                    'error' => $process->getErrorOutput(),
                    'output' => $process->getOutput(),
                ]);

                return false;
            }

            if (realpath($generatedPdfPath) !== realpath($pdfPath)) {
                if (file_exists($pdfPath)) {
                    @unlink($pdfPath);
                }

                rename($generatedPdfPath, $pdfPath);
            }

            return true;
        } finally {
            if ($profileDir !== null) {
                $this->deleteDirectory($profileDir);
            }
        }
    }

    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    private function resolveBinary(): ?string
    {
        $envPath = trim((string) env('LIBREOFFICE_PATH', ''));

        $candidates = array_filter([
            $envPath !== '' ? $envPath : null,
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/usr/local/bin/soffice',
            '/usr/local/bin/libreoffice',
            '/snap/bin/soffice',
            '/snap/bin/libreoffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
            'C:\\Program Files\\LibreOffice\\program\\soffice.com',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        $finder = new ExecutableFinder();

        return $finder->find('soffice')
            ?? $finder->find('libreoffice')
            ?? $finder->find('soffice.com');
    }

    private function resolveTimeoutSeconds(): float
    {
        $raw = (int) env('LIBREOFFICE_TIMEOUT_SECONDS', self::DEFAULT_TIMEOUT_SECONDS);

        return (float) max(10, $raw);
    }

    /**
     * @return array<string, string>
     */
    private function prepareRuntimePaths(): array
    {
        $runtimeRoot = storage_path('app/libreoffice-runtime');

        $paths = [
            'root' => $runtimeRoot,
            'home' => $runtimeRoot . DIRECTORY_SEPARATOR . 'home',
            'config' => $runtimeRoot . DIRECTORY_SEPARATOR . 'xdg-config',
            'cache' => $runtimeRoot . DIRECTORY_SEPARATOR . 'xdg-cache',
            'data' => $runtimeRoot . DIRECTORY_SEPARATOR . 'xdg-data',
            'tmp' => $runtimeRoot . DIRECTORY_SEPARATOR . 'tmp',
            'profiles' => $runtimeRoot . DIRECTORY_SEPARATOR . 'profiles',
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }

        return $paths;
    }

    /**
     * @param  array<string, string>  $runtimePaths
     * @return array<string, string>
     */
    private function buildProcessEnvironment(array $runtimePaths): array
    {
        $env = [
            'HOME' => $runtimePaths['home'],
            'USERPROFILE' => $runtimePaths['home'],
            'XDG_CONFIG_HOME' => $runtimePaths['config'],
            'XDG_CACHE_HOME' => $runtimePaths['cache'],
            'XDG_DATA_HOME' => $runtimePaths['data'],
            'TMPDIR' => $runtimePaths['tmp'],
            'TMP' => $runtimePaths['tmp'],
            'TEMP' => $runtimePaths['tmp'],
            'SAL_USE_VCLPLUGIN' => (string) env('LIBREOFFICE_SAL_VCLPLUGIN', 'svp'),
            'SAL_DISABLE_SYNCHRONOUS_PRINTER_DETECTION' => '1',
            'LANG' => (string) env('LIBREOFFICE_LANG', 'C.UTF-8'),
            'LC_ALL' => (string) env('LIBREOFFICE_LANG', 'C.UTF-8'),
        ];

        $fontConfigPath = trim((string) env('FONTCONFIG_PATH', ''));
        if ($fontConfigPath !== '') {
            $env['FONTCONFIG_PATH'] = $fontConfigPath;
        }

        $fontConfigFile = trim((string) env('FONTCONFIG_FILE', ''));
        if ($fontConfigFile !== '') {
            $env['FONTCONFIG_FILE'] = $fontConfigFile;
        }

        $path = getenv('PATH');
        if (is_string($path) && $path !== '') {
            $env['PATH'] = $path;
        }

        return $env;
    }

    private function resolveGeneratedPdfPath(string $outputDir, string $xlsxPath): ?string
    {
        $expected = $outputDir . DIRECTORY_SEPARATOR . pathinfo($xlsxPath, PATHINFO_FILENAME) . '.pdf';

        if (file_exists($expected)) {
            return $expected;
        }

        $candidates = $this->listPdfCandidates($outputDir);

        if ($candidates === []) {
            return null;
        }

        $expectedStem = $this->normalizeStem(pathinfo($xlsxPath, PATHINFO_FILENAME));

        foreach ($candidates as $candidate) {
            $candidateStem = $this->normalizeStem(pathinfo($candidate, PATHINFO_FILENAME));
            if ($candidateStem === $expectedStem) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function listPdfCandidates(string $outputDir): array
    {
        $globPattern = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.pdf';
        $pdfFiles = glob($globPattern);

        if ($pdfFiles === false || $pdfFiles === []) {
            return [];
        }

        usort($pdfFiles, static function (string $a, string $b): int {
            return (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0);
        });

        return $pdfFiles;
    }

    private function normalizeStem(string $stem): string
    {
        $value = strtolower($stem);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;
    }

    private function toFileUrl(string $path): string
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $normalized = str_replace('\\', '/', $normalized);

        if (preg_match('/^[a-zA-Z]:\//', $normalized) === 1) {
            return 'file:///' . rawurlencode(substr($normalized, 0, 1)) . '%3A' . str_replace('/', '%2F', substr($normalized, 2));
        }

        return 'file://' . $normalized;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
