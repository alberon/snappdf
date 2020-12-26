<?php

namespace Beganovich\Snappdf;

use Beganovich\Snappdf\Command\DownloadChromiumCommand;
use Beganovich\Snappdf\Exception\BinaryNotExecutable;
use Beganovich\Snappdf\Exception\BinaryNotFound;
use Beganovich\Snappdf\Exception\MissingContent;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Snappdf
{
    /**
     * @var string
     */
    private $chromiumPath;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $html;

    /**
     * @return string
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return Snappdf
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return string
     * @throws BinaryNotFound
     */
    public function getChromiumPath(): string
    {
        if ($this->chromiumPath) {
            return $this->chromiumPath;
        }

        if (getenv('SNAPPDF_EXECUTABLE_PATH')) {
            return getenv('SNAPPDF_EXECUTABLE_PATH');
        }

        $latestRevisionFile = dirname(__FILE__, 2) . '/versions/revision.txt';

        if (file_exists($latestRevisionFile)) {
            $chromuimBinary = (new DownloadChromiumCommand())->generatePlatformExecutable(
                file_get_contents($latestRevisionFile)
            );

            if (!is_executable($chromuimBinary)) {
                throw new BinaryNotExecutable('Downloaded Chromium binary is not executable. Make sure to set correct permissions (0755)');
            }

            return $chromuimBinary;
        }

        throw new BinaryNotFound('Browser binary not found. Make sure you download it or set using setChromiumPath().');
    }

    /**
     * @param string $chromiumPath
     * @return Snappdf
     */
    public function setChromiumPath(string $chromiumPath): self
    {
        $this->chromiumPath = $chromiumPath;

        return $this;
    }

    /**
     * @return string
     */
    public function getHtml(): ?string
    {
        return $this->html;
    }

    /**
     * @param string $html
     * @return Snappdf
     */
    public function setHtml(string $html): self
    {
        $this->html = $html;

        return $this;
    }

    /**
     * Main method to generate PDFs.
     * 
     * @throws \Beganovich\Snappdf\Exception\MissingContent 
     * @throws \Beganovich\Snappdf\Exception\BinaryNotFound 
     * @throws \Symfony\Component\Process\Exception\RuntimeException 
     * @throws \Symfony\Component\Process\Exception\ProcessTimedOutException 
     * @throws \Symfony\Component\Process\Exception\ProcessSignaledException 
     * @throws \Symfony\Component\Process\Exception\LogicException 
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException 
     * @return null|string 
     */
    public function generate(): ?string
    {
        $content = [
            'type' => null,
            'content' => null,
        ];

        if ($this->getUrl()) {
            $content['type'] = 'url';
            $content['content'] = $this->getUrl();
        }

        if ($this->getHtml()) {
            $temporaryFile = tempnam(sys_get_temp_dir(), 'html_');
            rename($temporaryFile, $temporaryFile .= '.html');
            file_put_contents($temporaryFile, $this->getHtml());

            $content['type'] = 'html';
            $content['content'] = $temporaryFile;
        }

        if (!$content['content']) {
            throw new MissingContent('No content provided. Make sure you call setHtml() or setUrl() before generate().');
        }

        $pdf = tempnam(sys_get_temp_dir(), 'pdf_');
        rename($pdf, $pdf .= '.pdf');

        $process = new Process([
            $this->getChromiumPath(),
            '--headless',
            '--disable-gpu',
            '--disable-translate',
            '--disable-extensions',
            '--disable-sync',
            '--disable-background-networking',
            '--disable-software-rasterizer',
            '--disable-default-apps',
            '--disable-dev-shm-usage',
            '--safebrowsing-disable-auto-update',
            '--run-all-compositor-stages-before-draw',
            '--no-first-run',
            '--no-margins',
            '--no-sandbox',
            '--print-to-pdf-no-header',
            '--hide-scrollbars',
            '--print-to-pdf=' . $pdf,
            $content['content'],
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return file_get_contents($pdf);
    }
}
