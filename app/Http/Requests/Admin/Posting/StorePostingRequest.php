<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Posting;

use App\Enums\Channel;
use App\Enums\Posting\PostingTransactionType;
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
            'amount'      => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(\.\d{1,2})?$/'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],

            'fee' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'vat' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'tax' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],


            'transaction_type' => ['nullable', Rule::in(PostingTransactionType::values())],
            'channel'          => ['nullable', Rule::in(Channel::values())],
            'priority'         => ['nullable', 'in:low,normal,high,urgent'],

            'device_id'  => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'location'   => ['nullable', 'string', 'max:255'],
            'remarks'    => ['nullable', 'string', 'max:1000'],
        ];
    }
}
