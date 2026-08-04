<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Enums\Customer\BusinessType;
use App\Enums\Customer\CustomerType;
use App\Enums\Customer\LegalForm;
use App\Models\AccountProduct;
use App\Models\Customer;
use App\Services\Account\AccountService;
use App\Services\Audit\AuditService;
use App\Services\Storage\FileStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelReader;

class CustomerService
{
    private const HASHABLE_FIELDS = ['password', 'panic_password', 'pin'];

    // Fields a bulk edit is allowed to touch — shared assignment fields only, never personal/identity data.
    private const BULK_EDITABLE_FIELDS = ['branch_id', 'account_officer_id'];

    public function __construct(
        private AuditService $audit,
        private AccountService $accounts,
        private DocumentService $documents,
        private FileStorageService $files,
    ) {}

    public function list(int $perPage = 15, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        return $this->filtered($search, $status)
            ->with('accounts:id,customer_id,account_number,status')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function cursorForExport(?string $search = null, ?string $status = null): LazyCollection
    {
        return $this->filtered($search, $status)
            ->with(['branch', 'accountOfficer'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursor();
    }

    public function countForFilter(?string $search = null, ?string $status = null): int
    {
        return $this->filtered($search, $status)->count();
    }

    private function filtered(?string $search = null, ?string $status = null): Builder
    {
        return Customer::query()
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('cif_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            });
    }

    private const RELATIONS = ['branch', 'accountOfficer', 'documents', 'currentAddress', 'nextOfKin', 'business', 'directors', 'signatories', 'accounts.accountProduct', 'accounts.accountTier', 'createdBy', 'approvedBy'];

    public function find(int $id): Customer
    {
        return Customer::with(self::RELATIONS)->findOrFail($id);
    }

    public function findByCifNumber(string $cifNumber): ?Customer
    {
        return Customer::where('cif_number', $cifNumber)->first();
    }

    public function create(array $data): Customer
    {
        $actor = Auth::guard('user')->user();

        $profilePicture = $data['profile_picture'] ?? null;
        $utilityBill    = $data['utility_bill'] ?? null;
        $validId        = $data['valid_id'] ?? null;
        $businessLogo   = $data['business_logo'] ?? null;
        $address        = $data['address'] ?? null;
        $nextOfKin      = $data['next_of_kin'] ?? [];
        $business       = $data['business'] ?? null;
        $directors      = $data['directors'] ?? [];
        $signatories    = $data['signatories'] ?? [];

        unset(
            $data['profile_picture'], $data['utility_bill'], $data['valid_id'], $data['business_logo'],
            $data['address'], $data['next_of_kin'], $data['business'], $data['directors'], $data['signatories'],
        );

        unset(
            $data['cif_number'], $data['status'],
            $data['approved_by'], $data['approved_at'],
            $data['rejected_by'], $data['rejected_at'], $data['rejection_reason'],
            $data['closed_by'], $data['closed_at'], $data['closure_reason'],
            $data['id_verified'], $data['face_verified'], $data['utility_verified'],
            $data['otp'], $data['otp_verified'],
            $data['enable_internet_bank'], $data['enable_sms'], $data['enable_email'],
            $data['enable_reset_password'], $data['enable_panic_password'],
            $data['referral_code'],
        );

        $data['cif_number']     = $this->generateUniqueCifNumber();
        $data['referral_code']  = $this->generateUniqueReferralCode();
        $data['panic_password'] = null; // Not offered at onboarding yet — always null regardless of what's sent.
        $data['created_by']     = $actor?->id;

        if ($business) {
            $data['business_name'] = $business['business_name'] ?? null;
        }
        $data['status']        = 'pending';

        // Credentials are auto-generated at onboarding rather than collected on the form:
        // username from the customer's name, pin is a fixed default, password is the BVN.
        if (empty($data['username'])) {
            $data['username'] = $this->generateUniqueUsername($data['first_name'] ?? null, $data['last_name'] ?? null, $data['business_name'] ?? null);
        }
        if (empty($data['pin'])) {
            $data['pin'] = '0000';
        }
        if (empty($data['password']) && ! empty($data['bvn'])) {
            $data['password'] = $data['bvn'];
        }

        foreach (self::HASHABLE_FIELDS as $field) {
            if (! empty($data[$field])) {
                $data[$field] = bcrypt($data[$field]);
            }
        }

        $customer = DB::transaction(function () use ($data, $actor, $profilePicture, $utilityBill, $validId, $businessLogo, $address, $nextOfKin, $business, $directors, $signatories) {
            $customer = Customer::create($data);

            $documentFiles = [
                'profile_picture' => $profilePicture,
                'utility_bill'    => $utilityBill,
                'valid_id'        => $validId,
                'business_logo'   => $businessLogo,
            ];

            foreach ($documentFiles as $type => $file) {
                if ($file instanceof UploadedFile) {
                    $customer->documents()->create([
                        'title'       => Str::of($type)->replace('_', ' ')->title()->value(),
                        'type'        => $type,
                        'path'        => $this->files->upload($file, "customer-documents/{$customer->id}"),
                        'uploaded_by' => $actor?->id,
                        'status'      => 'pending',
                    ]);
                }
            }

            if ($address) {
                $address['status'] = 'current';
                $customer->addresses()->create($address);
            }

            foreach ($nextOfKin as $kin) {
                $customer->nextOfKin()->create($kin);
            }

            if ($business) {
                $customer->business()->create($business);
            }

            foreach ($directors as $director) {
                if (($director['passport_photo'] ?? null) instanceof UploadedFile) {
                    $director['passport_photo'] = $this->files->upload($director['passport_photo'], "director-photos/{$customer->id}");
                }

                $customer->directors()->create($director);
            }

            foreach ($signatories as $signatory) {
                if (($signatory['passport_photo'] ?? null) instanceof UploadedFile) {
                    $signatory['passport_photo'] = $this->files->upload($signatory['passport_photo'], "signatory-photos/{$customer->id}");
                }

                if (($signatory['signature'] ?? null) instanceof UploadedFile) {
                    $signatory['signature'] = $this->files->upload($signatory['signature'], "signatory-signatures/{$customer->id}");
                }

                $customer->signatories()->create($signatory);
            }

            return $customer;
        });

        $customer = $customer->fresh(self::RELATIONS);

        $this->audit->log(
            action: 'created',
            module: 'customers',
            auditable: $customer,
            after: [
                'cif_number'    => $customer->cif_number,
                'phone'         => $customer->phone,
                'status'        => $customer->status,
                'customer_type' => $customer->customer_type,
            ],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was onboarded by '{$actor?->username}' and is pending approval.",
        );

        return $customer;
    }

    // Column headers for the bulk-import template/upload — covers both customer types
    // (individual, business) on one flat row each; a minor is an individual with
    // guardian_account_number filled in, "corporate" isn't a separate type at all — it's a
    // business row with director rows added, which this flat format doesn't carry
    // (directors are optional per StoreCustomerRequest's own rules and left out here; a
    // business needing directors should go through the normal onboarding form for that
    // part) — and "government" isn't a separate type either, it's a business row with
    // legal_form = 'government'. Business-like rows carry a single primary signatory only.
    public const BULK_IMPORT_COLUMNS = [
        'customer_type', 'legal_form',
        'first_name', 'middle_name', 'last_name', 'phone', 'email', 'gender', 'dob',
        'marital_status', 'occupation', 'bvn', 'nin_number',
        'address', 'city', 'state', 'country',
        'next_of_kin_first_name', 'next_of_kin_last_name', 'next_of_kin_relationship', 'next_of_kin_phone',
        'guardian_account_number',
        'business_name', 'business_type', 'trading_name', 'registration_number',
        'signatory_first_name', 'signatory_last_name', 'signatory_phone', 'signatory_position',
    ];

    // Human-readable column headers for the downloadable template — the raw snake_case
    // BULK_IMPORT_COLUMNS keys are an internal implementation detail and shouldn't be shown
    // to admins filling in the sheet. SimpleExcelReader::headersToSnakeCase() (used in
    // bulkImport() below) converts these labels straight back to the internal keys on
    // upload, so this is purely cosmetic — no change needed on the read side.
    private const BULK_IMPORT_COLUMN_LABELS = [
        'customer_type'            => 'Customer Type',
        'legal_form'                => 'Legal Form',
        'first_name'                => 'First Name',
        'middle_name'               => 'Middle Name',
        'last_name'                 => 'Last Name',
        'phone'                     => 'Phone',
        'email'                     => 'Email',
        'gender'                    => 'Gender',
        'dob'                       => 'DOB',
        'marital_status'            => 'Marital Status',
        'occupation'                => 'Occupation',
        'bvn'                       => 'BVN',
        'nin_number'                => 'NIN Number',
        'address'                   => 'Address',
        'city'                      => 'City',
        'state'                     => 'State',
        'country'                   => 'Country',
        'next_of_kin_first_name'    => 'Next of Kin First Name',
        'next_of_kin_last_name'     => 'Next of Kin Last Name',
        'next_of_kin_relationship'  => 'Next of Kin Relationship',
        'next_of_kin_phone'         => 'Next of Kin Phone',
        'guardian_account_number'   => 'Guardian Account Number',
        'business_name'             => 'Business Name',
        'business_type'             => 'Business Type',
        'trading_name'              => 'Trading Name',
        'registration_number'       => 'Registration Number',
        'signatory_first_name'      => 'Signatory First Name',
        'signatory_last_name'       => 'Signatory Last Name',
        'signatory_phone'           => 'Signatory Phone',
        'signatory_position'        => 'Signatory Position',
    ];

    /**
     * One fully-filled example row per customer type — every column that actually applies
     * to that type carries a realistic sample value (not left blank just because it
     * happened to inherit that way from another row), so the template is instructive on
     * its own without needing extra documentation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bulkImportTemplateRows(): array
    {
        $blank = array_fill_keys(self::BULK_IMPORT_COLUMNS, '');

        $individual = array_merge($blank, [
            'customer_type'            => 'individual',
            'first_name'               => 'Jane',
            'middle_name'              => 'Ade',
            'last_name'                => 'Doe',
            'phone'                    => '08012345678',
            'email'                    => 'jane.doe@example.com',
            'gender'                   => 'female',
            'dob'                      => '1990-01-15',
            'marital_status'           => 'single',
            'occupation'               => 'Trader',
            'bvn'                      => '12345678901',
            'nin_number'               => '12345678901',
            'address'                  => '12 Marina Street',
            'city'                     => 'Lagos',
            'state'                    => 'Lagos',
            'country'                  => 'Nigeria',
            'next_of_kin_first_name'   => 'John',
            'next_of_kin_last_name'    => 'Doe',
            'next_of_kin_relationship' => 'Spouse',
            'next_of_kin_phone'        => '08098765432',
        ]);

        // A minor is not its own customer_type — it's an individual with
        // guardian_account_number filled in (resolved to guardian_id below).
        $minor = array_merge($blank, [
            'customer_type'            => 'individual',
            'first_name'               => 'Baby',
            'middle_name'              => 'Tolu',
            'last_name'                => 'Doe',
            'phone'                    => '08011112222',
            'gender'                   => 'female',
            'dob'                      => '2015-06-01',
            'address'                  => '12 Marina Street',
            'city'                     => 'Lagos',
            'state'                    => 'Lagos',
            'country'                  => 'Nigeria',
            'next_of_kin_first_name'   => 'John',
            'next_of_kin_last_name'    => 'Doe',
            'next_of_kin_relationship' => 'Father',
            'next_of_kin_phone'        => '08098765432',
            'guardian_account_number'  => '1000000001',
        ]);

        $business = array_merge($blank, [
            'customer_type'        => 'business',
            'first_name'           => 'Chidi',
            'middle_name'          => 'Emeka',
            'last_name'            => 'Okafor',
            'phone'                => '08022223333',
            'email'                => 'chidi.okafor@example.com',
            'gender'               => 'male',
            'dob'                  => '1985-03-20',
            'marital_status'       => 'married',
            'occupation'           => 'Business Owner',
            'bvn'                  => '22345678901',
            'nin_number'           => '22345678901',
            'address'              => '45 Broad Street',
            'city'                 => 'Lagos',
            'state'                => 'Lagos',
            'country'              => 'Nigeria',
            'business_name'        => 'Okafor Ventures',
            'business_type'        => 'business_name',
            'trading_name'         => 'Okafor Stores',
            'registration_number'  => 'BN9876543',
            'signatory_first_name' => 'Chidi',
            'signatory_last_name'  => 'Okafor',
            'signatory_phone'      => '08022223333',
            'signatory_position'   => 'Proprietor',
        ]);

        $government = array_merge($blank, [
            'customer_type'        => 'business',
            'legal_form'           => 'government',
            'first_name'           => 'Amina',
            'middle_name'          => 'Bello',
            'last_name'            => 'Yusuf',
            'phone'                => '08044445555',
            'email'                => 'amina.yusuf@example.gov.ng',
            'gender'               => 'female',
            'dob'                  => '1978-11-02',
            'marital_status'       => 'married',
            'occupation'           => 'Civil Servant',
            'bvn'                  => '33345678901',
            'nin_number'           => '33345678901',
            'address'              => '1 Secretariat Road',
            'city'                 => 'Abuja',
            'state'                => 'FCT',
            'country'              => 'Nigeria',
            'business_name'        => 'Ministry of Works',
            'business_type'        => 'government',
            'trading_name'         => '',
            'registration_number'  => 'GOV-0001',
            'signatory_first_name' => 'Amina',
            'signatory_last_name'  => 'Yusuf',
            'signatory_phone'      => '08044445555',
            'signatory_position'   => 'Permanent Secretary',
        ]);

        return array_map(
            fn (array $row) => $this->relabelBulkImportRow($row),
            [$individual, $minor, $business, $government],
        );
    }

    // Swaps a row's internal snake_case keys for the human-readable headers an admin should
    // actually see in the downloaded template (see BULK_IMPORT_COLUMN_LABELS above).
    private function relabelBulkImportRow(array $row): array
    {
        $labelled = [];

        foreach ($row as $column => $value) {
            $labelled[self::BULK_IMPORT_COLUMN_LABELS[$column] ?? $column] = $value;
        }

        return $labelled;
    }

    /**
     * Parses an uploaded spreadsheet and creates one customer per row (either of the 2
     * customer types — individual [optionally a minor, via guardian_account_number] or
     * business [optionally government, via legal_form] — see mapBulkImportRow()), isolating each row in its own
     * try/catch (mirroring PostingService::bulkApprove()'s pattern) so a bad row
     * (duplicate phone, missing name, whatever) never blocks or rolls back the rest of the
     * batch.
     *
     * @return array{created: int, failed: int, errors: array<int, array{row: int, reason: string}>}
     */
    public function bulkImport(UploadedFile $file): array
    {
        $created = 0;
        $errors  = [];

        $rows = SimpleExcelReader::create($file->getRealPath(), $file->getClientOriginalExtension())
            ->headersToSnakeCase()
            ->getRows();

        $rowNumber = 1; // Row 1 is the header row, so the first data row is row 2.

        foreach ($rows as $row) {
            $rowNumber++;

            try {
                $data = $this->mapBulkImportRow($row);
            } catch (ValidationException $e) {
                $errors[] = ['row' => $rowNumber, 'reason' => implode(' ', $e->errors()[array_key_first($e->errors())] ?? ['Invalid row.'])];
                continue;
            }

            $validator = Validator::make($data, $this->bulkImportRulesFor($data));

            if ($validator->fails()) {
                $errors[] = ['row' => $rowNumber, 'reason' => implode(' ', $validator->errors()->all())];
                continue;
            }

            try {
                $this->create($data);
                $created++;
            } catch (\Throwable $e) {
                report($e);
                $errors[] = ['row' => $rowNumber, 'reason' => 'This row could not be processed — please check its values and try again.'];
            }
        }

        return ['created' => $created, 'failed' => count($errors), 'errors' => $errors];
    }

    /** @param array<string, mixed> $data
     *  @return array<string, array<int, mixed>> */
    private function bulkImportRulesFor(array $data): array
    {
        $customerType = $data['customer_type'] ?? CustomerType::INDIVIDUAL->value;

        $rules = [
            'customer_type' => ['required', Rule::in(CustomerType::values())],
            'legal_form'    => ['nullable', Rule::in(LegalForm::values())],
            'first_name'    => ['required', 'string', 'max:255'],
            'last_name'     => ['required', 'string', 'max:255'],
            'phone'         => ['required', 'string', 'regex:/^\d+$/', 'max:15', 'unique:customers,phone'],
            'email'         => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'gender'        => ['nullable', 'in:male,female,other'],
            'dob'           => ['nullable', 'date', 'before:today'],
            'bvn'           => ['nullable', 'digits:11', 'unique:customers,bvn'],
            'nin_number'    => ['nullable', 'digits:11', 'unique:customers,nin_number'],
        ];

        // Minor isn't a separate customer_type — it's an individual with guardian_id set.
        // guardian_id itself is resolved (and validated as a real account) in
        // mapBulkImportRow() before this runs; nothing extra to require here.

        if (in_array($customerType, CustomerType::businessLike(), true)) {
            $rules['business.business_name'] = ['required', 'string', 'max:255'];
            $rules['business.business_type'] = ['required', Rule::in(BusinessType::values())];

            $isUnregistered = ($data['business']['business_type'] ?? null) === BusinessType::NOT_REGISTERED->value;

            if (! $isUnregistered) {
                $rules['signatories.0.first_name'] = ['required', 'string', 'max:255'];
                $rules['signatories.0.last_name']  = ['required', 'string', 'max:255'];
                $rules['signatories.0.phone']      = ['required', 'string', 'regex:/^\d+$/', 'max:15'];
                $rules['signatories.0.position']   = ['required', 'string', 'max:100'];
            }
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $row
     * @throws ValidationException if guardian_account_number is set but doesn't resolve to a real account
     */
    private function mapBulkImportRow(array $row): array
    {
        $customerType = trim((string) ($row['customer_type'] ?? '')) ?: CustomerType::INDIVIDUAL->value;

        if (! in_array($customerType, CustomerType::values(), true)) {
            throw ValidationException::withMessages([
                'customer_type' => ["'{$customerType}' is not a valid customer type."],
            ]);
        }

        $data = [
            'customer_type'   => $customerType,
            'legal_form'      => trim((string) ($row['legal_form'] ?? '')) ?: null,
            'first_name'      => trim((string) ($row['first_name'] ?? '')) ?: null,
            'middle_name'     => trim((string) ($row['middle_name'] ?? '')) ?: null,
            'last_name'       => trim((string) ($row['last_name'] ?? '')) ?: null,
            'phone'           => trim((string) ($row['phone'] ?? '')) ?: null,
            'email'           => trim((string) ($row['email'] ?? '')) ?: null,
            'gender'          => trim((string) ($row['gender'] ?? '')) ?: null,
            'dob'             => trim((string) ($row['dob'] ?? '')) ?: null,
            'marital_status'  => trim((string) ($row['marital_status'] ?? '')) ?: null,
            'occupation'      => trim((string) ($row['occupation'] ?? '')) ?: null,
            'bvn'             => trim((string) ($row['bvn'] ?? '')) ?: null,
            'nin_number'      => trim((string) ($row['nin_number'] ?? '')) ?: null,
        ];

        $address = array_filter([
            'address' => trim((string) ($row['address'] ?? '')) ?: null,
            'city'    => trim((string) ($row['city'] ?? '')) ?: null,
            'state'   => trim((string) ($row['state'] ?? '')) ?: null,
            'country' => trim((string) ($row['country'] ?? '')) ?: null,
        ]);

        if ($address) {
            $data['address'] = $address;
        }

        // Next of kin — individuals only (businessLike rejects it entirely, same as the
        // normal onboarding form). Applies whether or not this individual is a minor.
        if (! in_array($customerType, CustomerType::businessLike(), true)) {
            $nextOfKinFirstName = trim((string) ($row['next_of_kin_first_name'] ?? ''));
            $nextOfKinLastName  = trim((string) ($row['next_of_kin_last_name'] ?? ''));
            $nextOfKinPhone     = trim((string) ($row['next_of_kin_phone'] ?? ''));

            if ($nextOfKinFirstName && $nextOfKinLastName && $nextOfKinPhone) {
                $data['next_of_kin'] = [[
                    'first_name'   => $nextOfKinFirstName,
                    'last_name'    => $nextOfKinLastName,
                    'relationship' => trim((string) ($row['next_of_kin_relationship'] ?? '')) ?: null,
                    'phone'        => $nextOfKinPhone,
                ]];
            }
        }

        // Minor isn't a separate customer_type — it's an individual with guardian_id set.
        // guardian_account_number is optional; when filled in (for any individual row), it's
        // resolved by account number (not CIF), matching the same account-number-first
        // lookup pattern used for investment funding accounts. A spreadsheet can't reference
        // another row's not-yet-created id, so the guardian must already have an account.
        $guardianAccountNumber = trim((string) ($row['guardian_account_number'] ?? ''));

        if ($guardianAccountNumber !== '') {
            $guardianAccount = $this->accounts->findByAccountNumber($guardianAccountNumber);

            if (! $guardianAccount || ! $guardianAccount->customer) {
                throw ValidationException::withMessages([
                    'guardian_account_number' => ["No account found with account number '{$guardianAccountNumber}'."],
                ]);
            }

            $data['guardian_id'] = $guardianAccount->customer->id;
        }

        // Business (including government, via legal_form) — a single primary signatory only (no directors; see the
        // BULK_IMPORT_COLUMNS comment for why). Not required at all when the business is
        // not_registered, matching StoreCustomerRequest's own rule.
        if (in_array($customerType, CustomerType::businessLike(), true)) {
            $businessType = trim((string) ($row['business_type'] ?? ''));

            $data['business'] = array_filter([
                'business_name'       => trim((string) ($row['business_name'] ?? '')) ?: null,
                'business_type'       => $businessType ?: null,
                'trading_name'        => trim((string) ($row['trading_name'] ?? '')) ?: null,
                'registration_number' => trim((string) ($row['registration_number'] ?? '')) ?: null,
            ]);

            if ($businessType !== BusinessType::NOT_REGISTERED->value) {
                $signatoryFirstName = trim((string) ($row['signatory_first_name'] ?? ''));
                $signatoryLastName  = trim((string) ($row['signatory_last_name'] ?? ''));
                $signatoryPhone     = trim((string) ($row['signatory_phone'] ?? ''));
                $signatoryPosition  = trim((string) ($row['signatory_position'] ?? ''));

                $data['signatories'] = [[
                    'first_name' => $signatoryFirstName ?: null,
                    'last_name'  => $signatoryLastName ?: null,
                    'phone'      => $signatoryPhone ?: null,
                    'position'   => $signatoryPosition ?: null,
                ]];
            }
        }

        return $data;
    }

    // Customer type is permanent and accounts belong to their own model — neither is ever touched here, no matter what the caller sends.
    private const IMMUTABLE_FIELDS = [
        'cif_number', 'status', 'referral_code',
        'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
        'closed_by', 'closed_at', 'closure_reason',
        'id_verified', 'face_verified', 'utility_verified',
        'otp', 'otp_verified',
        'created_at', 'created_by',
        'accounts', 'account_id', 'account_ids',
    ];

    public function update(Customer $customer, array $data): Customer
    {
        if (array_key_exists('customer_type', $data) && $data['customer_type'] !== $customer->customer_type) {
            throw ValidationException::withMessages([
                'customer_type' => ["Customer type cannot be changed once set. This customer will remain '{$customer->customer_type}' forever."],
            ]);
        }

        $profilePicture = $data['profile_picture'] ?? null;
        $utilityBill    = $data['utility_bill'] ?? null;
        $validId        = $data['valid_id'] ?? null;
        $businessLogo   = $data['business_logo'] ?? null;
        $address        = $data['address'] ?? null;
        $nextOfKin      = $data['next_of_kin'] ?? [];
        $business       = $data['business'] ?? null;
        $directors      = $data['directors'] ?? [];
        $signatories    = $data['signatories'] ?? [];

        unset(
            $data['profile_picture'], $data['utility_bill'], $data['valid_id'], $data['business_logo'],
            $data['address'], $data['next_of_kin'], $data['business'], $data['directors'], $data['signatories'],
        );

        $before = $customer->only(['phone', 'email', 'username', 'branch_id', 'account_officer_id']);
        $actor  = Auth::guard('user')->user();

        foreach (self::HASHABLE_FIELDS as $field) {
            if (! empty($data[$field])) {
                $data[$field] = bcrypt($data[$field]);
            }
        }

        unset($data['customer_type']);

        foreach (self::IMMUTABLE_FIELDS as $field) {
            unset($data[$field]);
        }

        DB::transaction(function () use ($customer, $data, $actor, $profilePicture, $utilityBill, $validId, $businessLogo, $address, $nextOfKin, $business, $directors, $signatories) {
            $customer->update($data);

            $documentFiles = [
                'profile_picture' => $profilePicture,
                'utility_bill'    => $utilityBill,
                'valid_id'        => $validId,
                'business_logo'   => $businessLogo,
            ];

            foreach ($documentFiles as $type => $file) {
                if ($file instanceof UploadedFile) {
                    $customer->documents()->create([
                        'title'       => Str::of($type)->replace('_', ' ')->title()->value(),
                        'type'        => $type,
                        'path'        => $this->files->upload($file, "customer-documents/{$customer->id}"),
                        'uploaded_by' => $actor?->id,
                        'status'      => 'pending',
                    ]);
                }
            }

            if ($address) {
                // Demote the existing current address to history, then insert the new one as current —
                // never overwrite in place, so the customer's address history stays queryable.
                $customer->addresses()->where('status', 'current')->update(['status' => 'previous']);

                $address['status'] = 'current';
                $customer->addresses()->create($address);
            }

            foreach ($nextOfKin as $kin) {
                $id = $kin['id'] ?? null;
                unset($kin['id']);

                if ($id) {
                    $customer->nextOfKin()->whereKey($id)->update($kin);
                    continue;
                }

                $customer->nextOfKin()->create($kin);
            }

            if ($business) {
                $customer->business()->updateOrCreate([], $business);
            }

            foreach ($directors as $director) {
                $id = $director['id'] ?? null;
                unset($director['id']);

                if (($director['passport_photo'] ?? null) instanceof UploadedFile) {
                    $director['passport_photo'] = $this->files->upload($director['passport_photo'], "director-photos/{$customer->id}");
                }

                if ($id) {
                    $customer->directors()->whereKey($id)->update($director);
                    continue;
                }

                $customer->directors()->create($director);
            }

            foreach ($signatories as $signatory) {
                $id = $signatory['id'] ?? null;
                unset($signatory['id']);

                if (($signatory['passport_photo'] ?? null) instanceof UploadedFile) {
                    $signatory['passport_photo'] = $this->files->upload($signatory['passport_photo'], "signatory-photos/{$customer->id}");
                }

                if (($signatory['signature'] ?? null) instanceof UploadedFile) {
                    $signatory['signature'] = $this->files->upload($signatory['signature'], "signatory-signatures/{$customer->id}");
                }

                if ($id) {
                    $customer->signatories()->whereKey($id)->update($signatory);
                    continue;
                }

                $customer->signatories()->create($signatory);
            }
        });

        $customer->refresh()->load(self::RELATIONS);

        $this->audit->log(
            action: 'updated',
            module: 'customers',
            auditable: $customer,
            before: $before,
            after: $customer->only(['phone', 'email', 'username', 'branch_id', 'account_officer_id']),
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was updated by '{$actor?->username}'.",
        );

        return $customer;
    }

    /**
     * Apply the same field values to many customers at once. Each customer is updated
     * independently — one bad id doesn't roll back the rest of the batch.
     *
     * @return array{updated: int[], skipped: array<int, array{customer_id: int, reason: string}>}
     */
    public function bulkUpdate(array $customerIds, array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::BULK_EDITABLE_FIELDS));

        $updated = [];
        $skipped = [];

        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);

            if (! $customer) {
                $skipped[] = ['customer_id' => $customerId, 'reason' => 'Customer not found.'];
                continue;
            }

            try {
                $this->update($customer, $data);
                $updated[] = $customer->id;
            } catch (ValidationException $e) {
                $skipped[] = ['customer_id' => $customerId, 'reason' => collect($e->errors())->flatten()->implode(' ')];
            }
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    // $data carries the admin-picked 'account_product_id' (and optional 'account_tier_id') to
    // open on approval — ApproveCustomerRequest requires it. No document or eligibility checks
    // are performed; approval itself no longer depends on documents.
    public function approve(Customer $customer, array $data = []): Customer
    {
        $actor = Auth::guard('user')->user();

        DB::transaction(function () use ($customer, $data, $actor) {
            // Locked + re-checked inside the transaction so a concurrent approve/reject
            // on the same customer can't both pass the status check and interleave —
            // whichever request gets here second sees the already-updated status.
            $lockedCustomer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ($lockedCustomer->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ["Only pending customers can be approved. This customer is currently '{$lockedCustomer->status}'."],
                ]);
            }

            $customer->update([
                'status'      => 'active',
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ]);

            // Approving the customer approves every still-pending document in one
            // go — reviewers can still approve/reject individual documents earlier,
            // but nothing should be left stuck at "pending" once the customer is active.
            $this->documents->approveAllPending($customer, $actor);

            // Kept inside the same transaction as the status change — if account
            // opening fails, the customer is rolled back to pending rather than
            // left permanently "active" with no account.
            if (! empty($data['account_product_id'])) {
                $accountProduct = AccountProduct::findOrFail($data['account_product_id']);

                $this->accounts->openAccountForCustomer($customer, [
                    'account_product_id' => $accountProduct->id,
                    'account_type'       => $accountProduct->product_type,
                    'account_tier_id'    => $data['account_tier_id'] ?? null,
                ]);
            }
        });

        $customer->refresh();

        $this->audit->log(
            action: 'approved',
            module: 'customers',
            auditable: $customer,
            before: ['status' => 'pending'],
            after: ['status' => $customer->status],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was approved by '{$actor?->username}'.",
        );

        return $customer;
    }

    public function reject(Customer $customer, string $reason): Customer
    {
        $actor = Auth::guard('user')->user();

        DB::transaction(function () use ($customer, $reason, $actor) {
            $lockedCustomer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ($lockedCustomer->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ["Only pending customers can be rejected. This customer is currently '{$lockedCustomer->status}'."],
                ]);
            }

            $customer->update([
                'status'           => 'rejected',
                'rejected_by'      => $actor?->id,
                'rejected_at'      => now(),
                'rejection_reason' => $reason,
            ]);
        });

        $customer->refresh();

        $this->audit->log(
            action: 'rejected',
            module: 'customers',
            auditable: $customer,
            before: ['status' => 'pending'],
            after: ['status' => $customer->status, 'rejection_reason' => $reason],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was rejected by '{$actor?->username}': {$reason}",
        );

        return $customer;
    }

    // Banking rule: an onboarded customer is never deleted — closing is a status change (with reason + audit trail), never a hard or soft delete.
    public function close(Customer $customer, string $reason): Customer
    {
        $actor  = Auth::guard('user')->user();
        $before = $customer->status;

        DB::transaction(function () use ($customer, $reason, $actor, &$before) {
            $lockedCustomer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $before         = $lockedCustomer->status;

            if ($lockedCustomer->status === 'closed') {
                throw ValidationException::withMessages([
                    'status' => ['This customer is already closed.'],
                ]);
            }

            $customer->update([
                'status'          => 'closed',
                'closed_by'       => $actor?->id,
                'closed_at'       => now(),
                'closure_reason'  => $reason,
            ]);
        });

        $customer->refresh();

        $this->audit->log(
            action: 'closed',
            module: 'customers',
            auditable: $customer,
            before: ['status' => $before],
            after: ['status' => $customer->status, 'closure_reason' => $reason],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) was closed by '{$actor?->username}': {$reason}",
        );

        return $customer;
    }

    public function toggleInternetBanking(Customer $customer): Customer
    {
        return $this->toggleFlag($customer, 'enable_internet_bank', 'internet banking access');
    }

    public function toggleSms(Customer $customer): Customer
    {
        return $this->toggleFlag($customer, 'enable_sms', 'SMS notifications');
    }

    public function toggleEmail(Customer $customer): Customer
    {
        return $this->toggleFlag($customer, 'enable_email', 'email notifications');
    }

    // Generic status change to any code in the `statuses` lookup table, with an optional
    // comment recorded on the audit trail (there's no dedicated column for it — unlike
    // approve/reject/close, this covers arbitrary transitions like dormant, frozen, kyc_verified).
    public function updateStatus(Customer $customer, string $status, ?string $comment = null): Customer
    {
        // A pending customer hasn't been vetted yet — it must go through approve()/reject()
        // first, not be nudged into an arbitrary status via this generic endpoint.
        if ($customer->status === 'pending') {
            throw ValidationException::withMessages([
                'status' => ['This customer is still pending approval. Approve or reject them before changing their status.'],
            ]);
        }

        $before = $customer->status;
        $actor  = Auth::guard('user')->user();

        $customer->update(['status' => $status]);
        $customer->refresh();

        $description = "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) status changed from '{$before}' to '{$customer->status}' by '{$actor?->username}'.";

        if ($comment) {
            $description .= " Comment: {$comment}";
        }

        $this->audit->log(
            action: 'status_updated',
            module: 'customers',
            auditable: $customer,
            before: ['status' => $before],
            after: ['status' => $customer->status, 'comment' => $comment],
            description: $description,
        );

        return $customer;
    }

    private function toggleFlag(Customer $customer, string $field, string $label): Customer
    {
        $before = (bool) $customer->{$field};
        $actor  = Auth::guard('user')->user();

        $customer->update([$field => ! $before]);
        $customer->refresh();

        $this->audit->log(
            action: 'updated',
            module: 'customers',
            auditable: $customer,
            before: [$field => $before],
            after: [$field => $customer->{$field}],
            description: "Customer '{$this->displayName($customer)}' ({$customer->cif_number}) {$label} was " . ($customer->{$field} ? 'enabled' : 'disabled') . " by '{$actor?->username}'.",
        );

        return $customer;
    }

    private function displayName(Customer $customer): string
    {
        return in_array($customer->customer_type, CustomerType::businessLike(), true)
            ? (string) $customer->business_name
            : trim("{$customer->first_name} {$customer->last_name}");
    }

    private function generateUniqueCifNumber(): string
    {
        $next = Customer::count() + 1;

        do {
            $cif = 'CIF' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $next++;
        } while (Customer::where('cif_number', $cif)->exists());

        return $cif;
    }

    private function generateUniqueReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Customer::where('referral_code', $code)->exists());

        return $code;
    }

    private function generateUniqueUsername(?string $firstName, ?string $lastName, ?string $businessName): string
    {
        $base = Str::slug(trim(($firstName ?? '') . ' ' . ($lastName ?? '')), '');

        if ($base === '') {
            $base = Str::slug($businessName ?? 'customer', '');
        }

        $username = $base;
        $suffix   = 1;

        while (Customer::where('username', $username)->exists()) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
    }
}
