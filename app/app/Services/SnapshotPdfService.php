<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class SnapshotPdfService
{
    public function download(string $title, string $svg, string $filename): Response
    {
        $svg = $this->applyPdfSvgFont($svg, "IPAGothic,IPAPGothic,'Noto Sans CJK JP',DejaVu Sans,sans-serif");
        $fontPath = $this->ensureFontFile();
        $tempFiles = [];
        $svgForPng = $this->prepareSvgForPng($svg, $tempFiles);
        $pngDataUri = $this->svgToPngDataUri($svgForPng, $tempFiles);

        $pdf = Pdf::loadView('pdf.snapshot', [
            'title' => $title,
            'svg' => $svg,
            'pngDataUri' => $pngDataUri,
            'fontPath' => $fontPath,
        ])->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('chroot', [base_path()])
            ->setOption('defaultFont', 'JPFont');

        return $pdf->download($filename);
    }

    private function prepareSvgForPng(string $svg, array &$tempFiles): string
    {
        return preg_replace_callback('/<image\\b[^>]*>/i', function (array $m) use (&$tempFiles) {
            $tag = $m[0];
            $attrs = $this->parseAttributes($tag);

            $href = $attrs['href'] ?? $attrs['xlink:href'] ?? null;
            if (!$href) return $tag;

            $resolved = $this->resolveImageHref($href, $tempFiles);
            if ($resolved) {
                $attrs['href'] = $resolved;
                unset($attrs['xlink:href']);
                return $this->buildImageTag($attrs);
            }

            return $tag;
        }, $svg) ?? $svg;
    }

    private function parseAttributes(string $tag): array
    {
        $attrs = [];
        if (preg_match_all('/([a-zA-Z_:][a-zA-Z0-9:._-]*)\\s*=\\s*\"([^\"]*)\"/', $tag, $m, PREG_SET_ORDER)) {
            foreach ($m as $set) {
                $attrs[$set[1]] = $set[2];
            }
        }
        return $attrs;
    }

    private function extractAttr(string $attrs, string $name): ?string
    {
        if (preg_match('/' . preg_quote($name, '/') . '\\s*=\\s*\"([^\"]+)\"/i', $attrs, $m)) {
            return $m[1];
        }
        return null;
    }

    private function parseLength(?string $value): ?float
    {
        if ($value === null) return null;
        if (preg_match('/([0-9]+(?:\\.[0-9]+)?)/', $value, $m)) {
            return (float)$m[1];
        }
        return null;
    }

    private function decodeDataUri(string $href): ?string
    {
        if (preg_match('#^data:([^;]+);base64,(.+)$#', $href, $m)) {
            return base64_decode($m[2]) ?: null;
        }
        if (preg_match('#^data:image/svg\\+xml;utf8,(.+)$#', $href, $m)) {
            return urldecode($m[1]);
        }
        if (preg_match('#^data:image/svg\\+xml,(.+)$#', $href, $m)) {
            return urldecode($m[1]);
        }
        return null;
    }

    private function buildImageTag(array $attrs): string
    {
        $pairs = [];
        foreach ($attrs as $k => $v) {
            $pairs[] = $k . '="' . htmlspecialchars($v, ENT_QUOTES) . '"';
        }
        return '<image ' . implode(' ', $pairs) . ' />';
    }

    private function parseViewBox(?string $viewBox, ?float $innerW, ?float $innerH, float $fallbackW, float $fallbackH): ?array
    {
        if ($viewBox) {
            $parts = preg_split('/\\s+/', trim($viewBox));
            if (count($parts) === 4) {
                return array_map('floatval', $parts);
            }
        }
        $w = $innerW ?? $fallbackW;
        $h = $innerH ?? $fallbackH;
        return [0.0, 0.0, (float)$w, (float)$h];
    }

    private function parsePreserveAspectRatio(?string $value): array
    {
        $alignX = 0.0;
        $alignY = 0.0;
        $meetOrSlice = 'meet';

        if ($value === null || $value === '') {
            return [$alignX, $alignY, $meetOrSlice];
        }

        $parts = preg_split('/\\s+/', trim($value));
        $align = $parts[0] ?? 'xMidYMid';
        $meetOrSlice = $parts[1] ?? 'meet';

        if ($align === 'none') {
            return [0.0, 0.0, 'none'];
        }

        $alignX = str_contains($align, 'xMid') ? 0.5 : (str_contains($align, 'xMax') ? 1.0 : 0.0);
        $alignY = str_contains($align, 'YMid') ? 0.5 : (str_contains($align, 'YMax') ? 1.0 : 0.0);

        return [$alignX, $alignY, $meetOrSlice];
    }

    private function ensureFontFile(): ?string
    {
        $candidates = [
            '/usr/share/fonts/truetype/ipafont/ipag.ttf',
            '/usr/share/fonts/truetype/ipafont/ipagp.ttf',
            '/usr/share/fonts/opentype/ipafont/ipag.ttf',
            '/usr/share/fonts/opentype/ipafont-gothic/ipag.ttf',
            '/usr/share/fonts/opentype/ipafont-gothic/ipagp.ttf',
            '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];

        $targetDir = storage_path('fonts');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $target = $targetDir . '/ipag.ttf';
        if (is_file($target)) {
            return 'storage/fonts/ipag.ttf';
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                @copy($path, $target);
                if (is_file($target)) {
                    return 'storage/fonts/ipag.ttf';
                }
                return $path;
            }
        }

        return null;
    }

    private function applyPdfSvgFont(string $svg, string $fontFamily): string
    {
        $styleOverride = 'text{font-family:' . $fontFamily . ' !important;}';

        if (preg_match('/<style>(.*?)<\\/style>/is', $svg, $m)) {
            $newStyle = $m[1] . $styleOverride;
            return str_replace($m[0], '<style>' . $newStyle . '</style>', $svg);
        }

        return preg_replace('/<svg\\b([^>]*)>/i', '<svg $1><style>' . $styleOverride . '</style>', $svg, 1) ?? $svg;
    }

    private function svgToPngDataUri(string $svg, array $tempFiles): ?string
    {
        return $this->convertSvgContentToPngDataUri($svg, $tempFiles);
    }

    private function convertSvgContentToPngDataUri(string $svg, array $tempFiles): ?string
    {
        $bin = trim((string)shell_exec('command -v rsvg-convert'));
        if ($bin === '') {
            return null;
        }

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $id = bin2hex(random_bytes(6));
        $svgPath = $tmpDir . '/snapshot_' . $id . '.svg';
        $pngPath = $tmpDir . '/snapshot_' . $id . '.png';

        file_put_contents($svgPath, $svg);

        $size = $this->extractSvgSize($svg);
        $cmd = escapeshellcmd($bin);
        if ($size) {
            $cmd .= ' --width ' . (int)$size['width'] . ' --height ' . (int)$size['height'];
        }
        $cmd .= ' -o ' . escapeshellarg($pngPath)
            . ' ' . escapeshellarg($svgPath);
        @exec($cmd, $out, $code);

        if ($code === 0 && is_file($pngPath)) {
            $data = base64_encode((string)file_get_contents($pngPath));
            @unlink($svgPath);
            @unlink($pngPath);
            $this->cleanupTempFiles($tempFiles);
            return 'data:image/png;base64,' . $data;
        }

        @unlink($svgPath);
        @unlink($pngPath);
        $this->cleanupTempFiles($tempFiles);
        return null;
    }

    private function embedRasterImage(array $attrs, string $abs): ?string
    {
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => null,
        };
        if (!$mime) return null;

        $data = base64_encode((string)file_get_contents($abs));
        $attrs['href'] = 'data:' . $mime . ';base64,' . $data;
        unset($attrs['xlink:href']);
        return $this->buildImageTag($attrs);
    }

    private function resolveImageHref(string $href, array &$tempFiles): ?string
    {
        if (str_starts_with($href, 'data:image/svg+xml')) {
            $svgContent = $this->decodeDataUri($href);
            if (!$svgContent) return null;
            $pngPath = $this->writeSvgPngTemp($svgContent, $tempFiles);
            return $pngPath ? 'file://' . $pngPath : null;
        }

        if (str_starts_with($href, 'data:image/')) {
            $data = $this->decodeDataUri($href);
            if ($data === null) return null;
            $tmp = $this->writeBinaryTemp($data, '.png', $tempFiles);
            return $tmp ? 'file://' . $tmp : null;
        }

        if (str_starts_with($href, 'file://')) {
            $path = substr($href, 7);
            return $this->resolveFileImage($path, $tempFiles);
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $href)) {
            return null;
        }

        $path = str_starts_with($href, '/') ? ltrim($href, '/') : $href;
        $abs = public_path($path);
        return $this->resolveFileImage($abs, $tempFiles);
    }

    private function resolveFileImage(string $abs, array &$tempFiles): ?string
    {
        if (!is_file($abs)) return null;
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $svgContent = (string)file_get_contents($abs);
            $pngPath = $this->writeSvgPngTemp($svgContent, $tempFiles);
            return $pngPath ? 'file://' . $pngPath : null;
        }
        return 'file://' . $abs;
    }

    private function writeSvgPngTemp(string $svgContent, array &$tempFiles): ?string
    {
        $bin = trim((string)shell_exec('command -v rsvg-convert'));
        if ($bin === '') {
            return null;
        }

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $id = bin2hex(random_bytes(6));
        $svgPath = $tmpDir . '/img_' . $id . '.svg';
        $pngPath = $tmpDir . '/img_' . $id . '.png';
        file_put_contents($svgPath, $svgContent);

        $size = $this->extractSvgSize($svgContent);
        $cmd = escapeshellcmd($bin);
        if ($size) {
            $cmd .= ' --width ' . (int)$size['width'] . ' --height ' . (int)$size['height'];
        }
        $cmd .= ' -o ' . escapeshellarg($pngPath)
            . ' ' . escapeshellarg($svgPath);
        @exec($cmd, $out, $code);

        $tempFiles[] = $svgPath;
        if ($code === 0 && is_file($pngPath)) {
            $tempFiles[] = $pngPath;
            return $pngPath;
        }

        return null;
    }

    private function writeBinaryTemp(string $data, string $suffix, array &$tempFiles): ?string
    {
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }
        $id = bin2hex(random_bytes(6));
        $path = $tmpDir . '/bin_' . $id . $suffix;
        file_put_contents($path, $data);
        $tempFiles[] = $path;
        return $path;
    }

    private function cleanupTempFiles(array $tempFiles): void
    {
        foreach ($tempFiles as $p) {
            @unlink($p);
        }
    }

    private function extractSvgSize(string $svg): ?array
    {
        if (!preg_match('/<svg\\b[^>]*>/i', $svg, $m)) {
            return null;
        }
        $tag = $m[0];
        $w = null;
        $h = null;
        if (preg_match('/\\bwidth\\s*=\\s*\"([^\"]+)\"/i', $tag, $wm)) {
            $w = $this->parseLength($wm[1]);
        }
        if (preg_match('/\\bheight\\s*=\\s*\"([^\"]+)\"/i', $tag, $hm)) {
            $h = $this->parseLength($hm[1]);
        }
        if (!$w || !$h) {
            return null;
        }
        return ['width' => $w, 'height' => $h];
    }
}
