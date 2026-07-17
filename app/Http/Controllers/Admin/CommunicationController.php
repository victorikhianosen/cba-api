<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Communication\SendBulkEmailRequest;
use App\Http\Requests\Admin\Communication\SendBulkSmsRequest;
use App\Http\Requests\Admin\Communication\SendEmailRequest;
use App\Http\Requests\Admin\Communication\SendSmsRequest;
use App\Http\Resources\Admin\Communication\CommunicationResource;
use App\Models\Customer;
use App\Services\Communication\CommunicationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommunicationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CommunicationService $communications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $communications = $this->communications->list(
            $request->integer('per_page', 20),
            $request->string('search')->value() ?: null,
            $request->string('channel')->value() ?: null,
            $request->string('status')->value() ?: null,
        );

        return $this->success(
            message: 'Communications retrieved successfully.',
            data: CommunicationResource::collection($communications),
        );
    }

    public function show(int $id): JsonResponse
    {
        $communication = $this->communications->find($id);

        return $this->success(
            message: 'Communication retrieved successfully.',
            data: new CommunicationResource($communication),
        );
    }

    public function sendEmail(SendEmailRequest $request): JsonResponse
    {
        return $this->sendSingle('email', $request->validated());
    }

    public function sendSms(SendSmsRequest $request): JsonResponse
    {
        return $this->sendSingle('sms', $request->validated());
    }

    public function sendBulkEmail(SendBulkEmailRequest $request): JsonResponse
    {
        return $this->sendBulk('email', $request->validated(), $request);
    }

    public function sendBulkSms(SendBulkSmsRequest $request): JsonResponse
    {
        return $this->sendBulk('sms', $request->validated(), $request);
    }

    private function sendSingle(string $channel, array $data): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($data['customer_id']);

            $recipient = $channel === 'email' ? $customer->email : $customer->phone;

            if (! $recipient) {
                throw ValidationException::withMessages([
                    'customer_id' => ["This customer has no {$channel} contact on file."],
                ]);
            }

            $communication = $this->communications->send(
                actor: $customer,
                channel: $channel,
                recipient: $recipient,
                body: $data['body'],
                subject: $data['subject'] ?? null,
                type: $data['type'] ?? 'manual',
            );

            return $this->success(
                message: $communication->status === 'sent'
                    ? ucfirst($channel) . ' sent successfully.'
                    : ucfirst($channel) . ' could not be delivered.',
                data: new CommunicationResource($communication),
                statusCode: $communication->status === 'sent' ? 201 : 422,
            );
        } catch (ValidationException $e) {
            return $this->error(
                message: $e->getMessage(),
                responseCode: '101',
                statusCode: 422,
                errors: $e->errors(),
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }

    private function sendBulk(string $channel, array $data, Request $request): JsonResponse
    {
        try {
            $result = $this->communications->sendBulkToCustomers(
                customerIds: $data['customer_ids'],
                channel: $channel,
                body: $data['body'],
                subject: $data['subject'] ?? null,
                type: $data['type'] ?? 'manual',
                performer: $request->user('user'),
            );

            return $this->success(
                message: 'Bulk ' . $channel . ' queued: ' . count($result['queued']) . ' accepted, ' . count($result['skipped']) . ' skipped.',
                data: [
                    'queued'  => $result['queued'],
                    'skipped' => $result['skipped'],
                ],
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error(
                message: 'We are unable to process your request please try again.',
                responseCode: '500',
                statusCode: 500,
            );
        }
    }
}
