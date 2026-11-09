<?php

namespace App\Services\Portfolio;

use DateTimeImmutable;
use RuntimeException;

class ContentDates
{
    public function format(?string $date): string
    {
        if ($date === null) {
            return '';
        }

        if (preg_match('/^\d{4}$/', $date)) {
            return $date;
        }

        $format = strlen($date) === 7 ? '!Y-m' : '!Y-m-d';
        $parsed = DateTimeImmutable::createFromFormat($format, $date);

        if ($parsed === false || DateTimeImmutable::getLastErrors() !== false) {
            throw new RuntimeException('Cannot display an invalid content date.');
        }

        return $parsed->format(strlen($date) === 7 ? 'M Y' : 'j M Y');
    }

    public function range(?string $start, ?string $end, bool $ongoing = false, bool $expected = false): string
    {
        $first = $this->format($start);
        $last = $ongoing ? 'Present' : $this->format($end);

        if ($expected && $last !== '') {
            $last = 'Expected '.$last;
        }

        return $first === '' ? $last : ($last === '' ? $first : $first.' — '.$last);
    }
}
