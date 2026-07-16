<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Invoice;

use OrderHub\Application\ReadModel\OrderSummary;

/**
 * Renders a minimal but structurally valid PDF (correct xref table and offsets)
 * for an order. Deliberately dependency-free — a real product would use a PDF
 * library, but the spec only asks for a simulated invoice.
 */
final class InvoicePdfRenderer
{
    public function render(OrderSummary $order): string
    {
        $lines = $this->buildTextLines($order);

        // Build the page content stream from the text lines.
        $content = "BT\n/F1 16 Tf\n50 790 Td\n(OrderHub - Nota Fiscal Simulada) Tj\n/F1 11 Tf\n";
        $y = 760;
        foreach ($lines as $line) {
            $content .= \sprintf("1 0 0 1 50 %d Tm\n(%s) Tj\n", $y, $this->escape($line));
            $y -= 18;
        }
        $content .= 'ET';

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
            . '/Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
        $objects[4] = '<< /Length ' . \strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        return $this->assemble($objects);
    }

    /**
     * @return list<string>
     */
    private function buildTextLines(OrderSummary $order): array
    {
        $lines = [
            'Pedido: ' . $order->orderId,
            'Cliente: ' . $order->customerName . '  <' . $order->customerEmail . '>',
            'Status: ' . $order->status,
            'Emitida em: ' . date('Y-m-d H:i'),
            '',
            'Itens:',
        ];
        foreach ($order->items as $item) {
            $lineTotal = number_format(($item['unitPriceCents'] * $item['quantity']) / 100, 2, ',', '.');
            $lines[] = \sprintf(
                '  %dx %s - %s %s',
                $item['quantity'],
                $item['productName'],
                $item['currency'],
                $lineTotal,
            );
        }
        $lines[] = '';
        $lines[] = 'TOTAL: ' . $order->currency . ' ' . number_format($order->totalCents / 100, 2, ',', '.');

        return $lines;
    }

    /**
     * @param array<int, string> $objects
     */
    private function assemble(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = \strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = \strlen($pdf);
        $count = \count($objects) + 1;
        $pdf .= "xref\n0 " . $count . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; ++$i) {
            $pdf .= \sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . $count . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
