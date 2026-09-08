<?php
/**
 * ABAppointments - Minimal dependency-free PDF writer
 *
 * Generates simple single/multi-page PDF documents (text, lines, rectangles)
 * using only the PDF standard Helvetica fonts, so no external library or
 * font file is required. Intentionally limited to what admin reports need:
 * absolutely positioned text and basic shapes.
 */
class PdfWriter {
    private float $width;
    private float $height;
    /** @var string[] Finished content streams, one per page */
    private array $pages = [];
    private string $buffer = '';
    private bool $hasPage = false;

    public function __construct(float $width = 595.28, float $height = 841.89) {
        $this->width = $width;
        $this->height = $height;
    }

    public function width(): float { return $this->width; }
    public function height(): float { return $this->height; }

    public function addPage(): void {
        if ($this->hasPage) {
            $this->pages[] = $this->buffer;
        }
        $this->buffer = '';
        $this->hasPage = true;
    }

    public function setFillColor(float $r, float $g, float $b): void {
        $this->buffer .= sprintf("%.3F %.3F %.3F rg\n", $r, $g, $b);
    }

    public function setStrokeColor(float $r, float $g, float $b): void {
        $this->buffer .= sprintf("%.3F %.3F %.3F RG\n", $r, $g, $b);
    }

    public function setLineWidth(float $w): void {
        $this->buffer .= sprintf("%.2F w\n", $w);
    }

    public function rect(float $x, float $y, float $w, float $h, bool $fill = false, bool $stroke = true): void {
        // PDF origin is bottom-left; callers think in top-left coordinates.
        $pdfY = $this->height - $y - $h;
        $this->buffer .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $pdfY, $w, $h, $this->paintOp($fill, $stroke));
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void {
        $this->buffer .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $x1, $this->height - $y1, $x2, $this->height - $y2);
    }

    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, array $color = [0, 0, 0]): void {
        $font = $bold ? 'F2' : 'F1';
        $encoded = $this->escape($this->toPdfEncoding($text));
        $this->buffer .= sprintf(
            "BT %.3F %.3F %.3F rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $color[0], $color[1], $color[2], $font, $size, $x, $this->height - $y, $encoded
        );
    }

    /** Width in points of $text set in Helvetica at $size, using approximate core-font metrics. */
    public function textWidth(string $text, float $size, bool $bold = false): float {
        // Average glyph width relative to font size for Helvetica (regular/bold).
        $avg = $bold ? 0.56 : 0.52;
        return mb_strlen($text) * $size * $avg;
    }

    /** Truncate $text with an ellipsis so it fits within $maxWidth points. */
    public function fitText(string $text, float $maxWidth, float $size, bool $bold = false): string {
        if ($this->textWidth($text, $size, $bold) <= $maxWidth) {
            return $text;
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $result = '';
        foreach ($chars as $ch) {
            if ($this->textWidth($result . $ch . '…', $size, $bold) > $maxWidth) {
                break;
            }
            $result .= $ch;
        }
        return $result . '…';
    }

    private function paintOp(bool $fill, bool $stroke): string {
        if ($fill && $stroke) return 'B';
        if ($fill) return 'f';
        return 'S';
    }

    private function toPdfEncoding(string $text): string {
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text);
        return $converted !== false ? $converted : $text;
    }

    private function escape(string $text): string {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /** Finalize the current page and return the full PDF file content. */
    public function output(): string {
        if ($this->hasPage) {
            $this->pages[] = $this->buffer;
            $this->hasPage = false;
        }
        $pageCount = count($this->pages);

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $pageObjNums = [];
        $firstPageObj = 5;
        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjNums[] = $firstPageObj + $i * 2;
        }
        $kids = implode(' ', array_map(fn($n) => "$n 0 R", $pageObjNums));
        $objects[2] = "<< /Type /Pages /Kids [$kids] /Count $pageCount >>";

        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        foreach ($this->pages as $i => $content) {
            $pageObj = $firstPageObj + $i * 2;
            $contentObj = $pageObj + 1;
            $objects[$pageObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->width} {$this->height}] "
                . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $contentObj 0 R >>";
            $objects[$contentObj] = [
                'stream' => $content,
            ];
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            if (is_array($body)) {
                $stream = $body['stream'];
                $pdf .= "$num 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream\nendobj\n";
            } else {
                $pdf .= "$num 0 obj\n$body\nendobj\n";
            }
        }

        $xrefStart = strlen($pdf);
        $maxObj = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($n = 1; $n <= $maxObj; $n++) {
            if (isset($offsets[$n])) {
                $pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
            } else {
                $pdf .= "0000000000 00000 f \n";
            }
        }
        $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n$xrefStart\n%%EOF";

        return $pdf;
    }
}
