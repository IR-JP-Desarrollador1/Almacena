<?php

namespace App\Support;

use App\Models\Company;
use Carbon\Carbon;
use DateTimeInterface;

class RegionalFormatter
{
    public function __construct(
        public readonly string $language,
        public readonly string $decimalSeparator,
        public readonly string $thousandsSeparator,
        public readonly string $dateFormat,
        public readonly string $timeFormat,
    ) {}

    public static function fromCompany(?Company $company): self
    {
        $countries = config('regional.countries');
        $countryCode = $company?->regional_country_code ?? 'CO';
        $preset = $countries[$countryCode] ?? $countries['CO'];

        return new self(
            $company?->language ?? $preset['language'],
            $company?->decimal_separator ?? $preset['decimal'],
            $company?->thousands_separator ?? $preset['thousands'],
            $company?->date_format ?? $preset['date'],
            $company?->time_format ?? $preset['time'],
        );
    }

    public function number(int|float|string|null $value, int $precision = 2): string
    {
        if (! is_numeric($value)) {
            return (string) ($value ?? '');
        }

        return number_format((float) $value, $precision, $this->decimalSeparator, $this->thousandsSeparator);
    }

    public function date(DateTimeInterface|string|null $value): string
    {
        return $value ? Carbon::parse($value)->format($this->dateFormat) : '—';
    }

    public function time(DateTimeInterface|string|null $value): string
    {
        return $value ? Carbon::parse($value)->format($this->timeFormat) : '—';
    }

    public function dateTime(DateTimeInterface|string|null $value): string
    {
        return $value ? $this->date($value).' '.$this->time($value) : '—';
    }
}
