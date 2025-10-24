<?php
declare(strict_types=1);

class SimplePDF
{
    private const PAGE_WIDTH = 595.28; // A4 width in points
    private const PAGE_HEIGHT = 841.89; // A4 height

    /** @var array<int,array{content:string}> */
    private array $pages = [];

    public function add_page(): void
    {
        $this->pages[] = ['content' => ''];
    }

    public function text(float $x, float $y, string $text, float $size = 11): void
    {
        if (empty($this->pages)) {
            $this->add_page();
        }

        $safeText = $this->escape_text($text);
        $content = sprintf(
            "BT /F1 %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
            $size,
            $x,
            self::PAGE_HEIGHT - $y,
            $safeText
        );

        $this->pages[count($this->pages) - 1]['content'] .= $content;
    }

    public function multi_text(float $x, float $startY, float $lineHeight, array $lines, float $size = 11): void
    {
        $y = $startY;
        foreach ($lines as $line) {
            $this->text($x, $y, $line, $size);
            $y += $lineHeight;
        }
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5): void
    {
        if (empty($this->pages)) {
            $this->add_page();
        }

        $content = sprintf(
            "%.2f w %.2f %.2f m %.2f %.2f l S\n",
            $width,
            $x1,
            self::PAGE_HEIGHT - $y1,
            $x2,
            self::PAGE_HEIGHT - $y2
        );

        $this->pages[count($this->pages) - 1]['content'] .= $content;
    }

    public function table(float $x, float $y, float $width, array $headers, array $rows, float $rowHeight = 18): void
    {
        $columns = count($headers);
        if ($columns === 0) {
            return;
        }

        $colWidth = $width / $columns;
        $currentY = $y;

        // Header row
        for ($i = 0; $i < $columns; $i++) {
            $this->text($x + $i * $colWidth + 4, $currentY + 5, (string)$headers[$i], 10);
        }
        $this->line($x, $currentY, $x + $width, $currentY);
        $currentY += $rowHeight;

        foreach ($rows as $row) {
            for ($i = 0; $i < $columns; $i++) {
                $value = $row[$i] ?? '';
                $this->text($x + $i * $colWidth + 4, $currentY + 5, (string)$value, 10);
            }
            $this->line($x, $currentY, $x + $width, $currentY);
            $currentY += $rowHeight;
        }

        $this->line($x, $y, $x, $currentY);
        $this->line($x + $width, $y, $x + $width, $currentY);
        for ($i = 1; $i < $columns; $i++) {
            $this->line($x + $i * $colWidth, $y, $x + $i * $colWidth, $currentY);
        }
    }

    public function output(string $filename = 'document.pdf'): void
    {
        if (headers_sent()) {
            throw new RuntimeException('Headers already sent.');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');

        echo $this->render();
    }

    public function render(): string
    {
        if (empty($this->pages)) {
            $this->add_page();
        }

        $objects = [];

        // Font object
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        // Content streams
        $contentObjectIds = [];
        foreach ($this->pages as $page) {
            $stream = $page['content'];
            $objects[] = sprintf("<< /Length %d >>\nstream\n%sendstream", strlen($stream), $stream);
            $contentObjectIds[] = count($objects);
        }

        // Page objects with placeholder for parent
        $pageObjectIds = [];
        foreach ($contentObjectIds as $index => $contentId) {
            $objects[] = sprintf(
                '<< /Type /Page /Parent {{PARENT}} 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 %d 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                1,
                $contentId
            );
            $pageObjectIds[] = count($objects);
        }

        // Pages object
        $objects[] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', array_map(fn($id) => $id . ' 0 R', $pageObjectIds)),
            count($pageObjectIds)
        );
        $pagesObjectId = count($objects);

        // Replace parent placeholder for page objects
        foreach ($pageObjectIds as $pageId) {
            $objects[$pageId - 1] = str_replace('{{PARENT}}', (string)$pagesObjectId, $objects[$pageId - 1]);
        }

        // Catalog
        $objects[] = sprintf('<< /Type /Catalog /Pages %d 0 R >>', $pagesObjectId);
        $catalogObjectId = count($objects);

        // Assemble PDF
        $result = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[$index + 1] = strlen($result);
            $result .= sprintf("%d 0 obj\n%s\nendobj\n", $index + 1, $object);
        }

        $xrefPosition = strlen($result);
        $result .= "xref\n";
        $result .= sprintf("0 %d\n", count($objects) + 1);
        $result .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $result .= sprintf("%010d 00000 n \n", $offset);
        }
        $result .= "trailer\n";
        $result .= sprintf("<< /Size %d /Root %d 0 R >>\n", count($objects) + 1, $catalogObjectId);
        $result .= "startxref\n";
        $result .= $xrefPosition . "\n";
        $result .= "%%EOF";

        return $result;
    }

    private function escape_text(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
