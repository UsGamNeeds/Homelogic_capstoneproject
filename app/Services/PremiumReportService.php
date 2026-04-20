<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PremiumReportService
{
    /**
     * Generate a professional PDF from a Blade view using Browsershot.
     * 
     * @param string $view The blade view name
     * @param array $data Data to pass to the view
     * @param string|null $filename Optional filename for the download or storage
     * @param array $options Additional Browsershot options
     * @return string The binary PDF content
     */
    public function generate(string $view, array $data = [], ?string $filename = null, array $options = []): string
    {
        Log::debug('Starting professional PDF generation', ['view' => $view, 'filename' => $filename]);

        // Render the HTML view
        $html = View::make($view, $data)->render();

        // Initialize Browsershot
        $browsershot = Browsershot::html($html)
            ->format($options['format'] ?? 'A4')
            ->margins(
                $options['margin_top'] ?? 10,
                $options['margin_right'] ?? 10,
                $options['margin_bottom'] ?? 10,
                $options['margin_left'] ?? 10
            )
            ->showBackground();

        // Handle orientation
        if (($options['orientation'] ?? 'portrait') === 'landscape') {
            $browsershot->landscape();
        }

        // Environment-aware binary paths
        $chromePath = env('CHROME_PATH', '/usr/bin/google-chrome');
        $possibleChromePaths = [
            $chromePath,
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/snap/bin/chromium',
            '/usr/bin/google-chrome'
        ];

        foreach ($possibleChromePaths as $path) {
            if ($path && file_exists($path)) {
                $browsershot->setChromePath($path);
                Log::debug('PremiumReportService: Using Chrome at ' . $path);
                break;
            }
        }

        $possibleNodePaths = [
            env('NODE_PATH'),
            '/usr/bin/node',
            '/usr/local/bin/node',
            '/home/forge/.nvm/versions/node/v20.11.0/bin/node', // Common Forge path
        ];

        foreach ($possibleNodePaths as $path) {
            if ($path && file_exists($path)) {
                $browsershot->setNodeBinary($path);
                Log::debug('PremiumReportService: Using Node at ' . $path);
                break;
            }
        }

        // Optimized settings for production stability
        $browsershot->timeout(180) // 3 minutes timeout for heavy reports
            ->noSandbox()
            ->addChromiumArguments([
                'disable-setuid-sandbox',
                'disable-dev-shm-usage',
                'disable-gpu',
                'disable-extensions',
                'font-render-hinting=none',
                'disable-web-security',
                'no-sandbox',
                'single-process'
            ]);

        try {
            Log::debug('PremiumReportService: Attempting browsershot->pdf()');
            $pdf = $browsershot->pdf();
            Log::debug('PremiumReportService: PDF generated successfully');
            return $pdf;
        } catch (\Exception $e) {
            Log::error('Browsershot PDF generation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'view' => $view
            ]);
            throw new \Exception("PDF Generation Error: " . $e->getMessage());
        }
    }
}
