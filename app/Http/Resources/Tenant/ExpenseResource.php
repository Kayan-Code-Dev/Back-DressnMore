<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_category_id' => $this->expense_category_id,
            'branch_id' => $this->branch_id,
            'cashbox_id' => $this->cashbox_id,
            'category' => $this->whenLoaded('category', function (): ?array {
                if ($this->category === null) {
                    return null;
                }

                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                    'status' => $this->category->status,
                ];
            }),
            'amount' => $this->amount,
            'status' => $this->status,
            'method' => $this->method,
            'vendor' => $this->vendor,
            'reference' => $this->reference,
            'reference_number' => $this->reference_number,
            'expense_date' => $this->expense_date?->toDateString(),
            'description' => $this->description,
            'notes' => $this->notes,
            'transaction_id' => $this->transaction_id,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'paid_at' => $this->paid_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
