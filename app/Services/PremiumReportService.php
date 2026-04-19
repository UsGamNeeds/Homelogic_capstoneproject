<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

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
            ->showBackground()
            ->waitUntilNetworkIdle();

        // Handle orientation
        if (($options['orientation'] ?? 'portrait') === 'landscape') {
            $browsershot->landscape();
        }

        // Use system chrome binary and required linux flags
        $browsershot->setChromePath('/usr/bin/google-chrome')
            ->addChromiumArguments([
                'no-sandbox',
                'disable-setuid-sandbox',
                'disable-dev-shm-usage',
                'disable-gpu'
            ]);

        return $browsershot->pdf();
    }
}
