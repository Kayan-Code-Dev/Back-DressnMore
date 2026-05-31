<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\StoreCustomerRequest;
use App\Http\Requests\Tenant\Customer\UpdateCustomerRequest;
use App\Http\Resources\Tenant\CustomerResource;
use App\Services\Tenant\CustomerService;
use App\Support\ApiResponse;
use App\Support\CsvExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customerService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $customers = $this->customerService->paginate([
            'search' => $request->query('search'),
            'id' => $request->query('id'),
            'source' => $request->query('source'),
            'status' => $request->query('status'),
            'date_of_birth_from' => $request->query('date_of_birth_from'),
            'date_of_birth_to' => $request->query('date_of_birth_to'),
        ], $perPage);

        return ApiResponse::paginated($customers, CustomerResource::collection($customers->items())->resolve());
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->customerService->stats());
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->create($request->validated());

        return ApiResponse::success(new CustomerResource($customer), 'Customer created', 201);
    }

    public function show(int $customer): JsonResponse
    {
        $customerModel = $this->customerService->findOrFail($customer);

        return ApiResponse::success(new CustomerResource($customerModel));
    }

    public function update(UpdateCustomerRequest $request, int $customer): JsonResponse
    {
        $customerModel = $this->customerService->findOrFail($customer);
        $customerModel = $this->customerService->update($customerModel, $request->validated());

        return ApiResponse::success(new CustomerResource($customerModel), 'Customer updated');
    }

    public function destroy(int $customer): JsonResponse
    {
        $customerModel = $this->customerService->findOrFail($customer);
        $this->customerService->delete($customerModel);

        return ApiResponse::success(null, 'Customer deleted');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->customerService->exportRows([
            'search' => $request->query('search'),
            'id' => $request->query('id'),
            'source' => $request->query('source'),
            'status' => $request->query('status'),
            'date_of_birth_from' => $request->query('date_of_birth_from'),
            'date_of_birth_to' => $request->query('date_of_birth_to'),
        ]);

        return CsvExporter::download(
            filename: 'customers.csv',
            headers: ['ID', 'Name', 'Date Of Birth', 'Source', 'Phone', 'Phone 2', 'WhatsApp', 'Address', 'City ID', 'Status'],
            rows: $rows,
        );
    }
}
