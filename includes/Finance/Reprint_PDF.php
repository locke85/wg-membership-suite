<?php

namespace wg\membership\Finance;

use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

class Reprint_PDF {
    public const TEMPLATE_VERSION = 'memberpress-history-copy-v1';

    public static function is_eligible(array $object) {
        if (($object['object_type'] ?? '') !== 'invoice' || trim((string) ($object['invoice_number'] ?? '')) === '') {
            return false;
        }
        $snapshot = json_decode((string) ($object['snapshot_json'] ?? ''), true);
        $member = is_array($snapshot['member'] ?? null) ? $snapshot['member'] : [];
        $membership = is_array($snapshot['membership'] ?? null) ? $snapshot['membership'] : [];
        return !empty($member['email'])
            && !empty($member['address'])
            && !empty($membership['title'])
            && !in_array((string) ($object['currency'] ?? ''), ['', 'XXX'], true)
            && $object['amount_net'] !== null
            && $object['amount_tax'] !== null
            && $object['amount_gross'] !== null;
    }

    public static function render(array $object) {
        if (!self::is_eligible($object)) {
            throw new RuntimeException('Der historische Datensatz ist fuer einen Reprint nicht vollstaendig.');
        }
        $snapshot = json_decode((string) $object['snapshot_json'], true);
        $member = (array) ($snapshot['member'] ?? []);
        $membership = (array) ($snapshot['membership'] ?? []);
        $lines = [
            'RECHNUNGSKOPIE',
            'Mehrstueck der historischen Rechnung',
            '',
            'Rechnungsnummer: ' . (string) $object['invoice_number'],
            'Ausstellungsdatum: ' . substr((string) $object['occurred_at'], 0, 10),
            'Empfaenger: ' . trim((string) ($member['first_name'] ?? '') . ' ' . (string) ($member['last_name'] ?? '')),
            'E-Mail: ' . (string) ($member['email'] ?? ''),
            'Leistung: ' . (string) ($membership['title'] ?? 'Historische MemberPress-Mitgliedschaft'),
            '',
            'Netto: ' . self::money($object['amount_net']) . ' ' . (string) $object['currency'],
            'Steuer: ' . self::money($object['amount_tax']) . ' ' . (string) $object['currency'],
            'Brutto: ' . self::money($object['amount_gross']) . ' ' . (string) $object['currency'],
            'Status: ' . (string) $object['status'],
            '',
            'Keine neue Rechnung. Keine neue Rechnungsnummer.',
            'Datenstand SHA-256: ' . (string) $object['object_hash'],
            'Vorlage: ' . self::TEMPLATE_VERSION,
        ];
        return self::minimal_pdf($lines);
    }

    private static function money($value) {
        return number_format((float) $value, 2, ',', '.');
    }

    private static function minimal_pdf(array $lines) {
        $commands = ["BT", "/F1 11 Tf", "50 790 Td", "14 TL"];
        foreach ($lines as $line) {
            $encoded = self::pdf_text((string) $line);
            $commands[] = '(' . $encoded . ') Tj';
            $commands[] = 'T*';
        }
        $commands[] = 'ET';
        $stream = implode("\n", $commands) . "\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . 'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private static function pdf_text($text) {
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
    }
}
