<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place that changes users.balance.
 * Every change locks the user row and writes a wallet_transactions entry.
 * Amounts are MMK, rounded to 2 decimals.
 */
class WalletService
{
    public function credit(User $user, float|string $amount, string $type, ?Model $reference = null, ?string $description = null): WalletTransaction
    {
        return $this->apply($user, $this->positive($amount), $type, $reference, $description);
    }

    public function debit(User $user, float|string $amount, string $type, ?Model $reference = null, ?string $description = null): WalletTransaction
    {
        return $this->apply($user, -$this->positive($amount), $type, $reference, $description);
    }

    private function positive(float|string $amount): float
    {
        $amount = round((float) $amount, 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return $amount;
    }

    private function apply(User $user, float $delta, string $type, ?Model $reference, ?string $description): WalletTransaction
    {
        return DB::transaction(function () use ($user, $delta, $type, $reference, $description) {
            /** @var User $locked */
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $newBalance = round((float) $locked->balance + $delta, 2);

            if ($newBalance < 0) {
                throw new InsufficientBalanceException;
            }

            $locked->forceFill(['balance' => $newBalance])->save();
            $user->setRawAttributes($locked->getAttributes(), true);

            return WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => $type,
                'amount' => $delta,
                'balance_after' => $newBalance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'created_by' => auth()->id(),
            ]);
        });
    }
}
