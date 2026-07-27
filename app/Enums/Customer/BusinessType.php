<?php

declare(strict_types=1);

namespace App\Enums\Customer;

enum BusinessType: string
{
    case LIMITED_LIABILITY = 'limited_liability';
    case COOPERATIVE = 'cooperative';
    case BUSINESS_NAME = 'business_name';
    case NOT_REGISTERED = 'not_registered';
    case GOVERNMENT = 'government';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /** Document types a business customer of this type must upload before approval. */
    public function requiredDocumentTypes(): array
    {
        return match ($this) {
            self::LIMITED_LIABILITY => [
                'cac_certificate',
                'cac_memart',
                'cac_status_report',
                'tax_identification_number',
                'board_resolution',
                'director_id_card',
                'utility_bill',
            ],
            self::BUSINESS_NAME => [
                'cac_certificate',
                'tax_identification_number',
                'director_id_card',
                'utility_bill',
            ],
            self::COOPERATIVE => [
                'cac_certificate',
                'executive_resolution',
                'director_id_card',
                'utility_bill',
            ],
            self::NOT_REGISTERED => [
                'utility_bill',
            ],
            self::GOVERNMENT => [
                'government_gazette',
                'appointment_letter',
                'tax_identification_number',
                'utility_bill',
            ],
        };
    }
}
