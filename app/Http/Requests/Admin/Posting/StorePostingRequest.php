<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Posting;

use App\Enums\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type'      => ['required', 'in:account,general_ledger'],
            'source_id'        => ['required', 'integer'],
            'destination_type' => ['required', 'in:account,general_ledger'],
            'destination_id'   => ['required', 'integer'],

            // Decimal amounts, at most 2 decimal places (kobo/cents precision) —
            // 0.01 is the smallest postable amount, 100.5 and 100.50 are fine,
            // 100.505 is not.
            'amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
            // currency_id is never accepted from the client — PostingService derives it from the
            // source party itself, so a Dollar-to-Naira posting can't happen even by mistake.

            'fee' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'vat' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'tax' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],


            // Free-text, not a hardcoded enum — new products (airtime, data, cable, electricity,
            // betting, interbank_transfer, intrabank_transfer, card_funding, ...) are just new
            // string values here, never a validation-rule or code change.
            'module'              => ['nullable', 'string', 'max:100'],
            'transaction_type'    => ['nullable', 'string', 'max:100'],
            'transaction_subtype' => ['nullable', 'string', 'max:100'],
            'business_service'    => ['nullable', 'string', 'max:150'],

            'channel'          => ['nullable', Rule::in(Channel::values())],
            'priority'         => ['nullable', 'in:low,normal,high,urgent'],

            'device_id'  => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'location'   => ['nullable', 'string', 'max:255'],
            'remarks'    => ['required', 'string', 'max:1000'],
        ];
    }
}
